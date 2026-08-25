<?php

namespace App\Imports;

use App\Imports\Concerns\TracksImportHistory;
use App\Models\EmployeeContract;
use App\Models\OnboardingCandidate;
use App\Models\ImportHistoryItem;
use App\Services\Vhire\VhireOnboardingContractService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\RemembersChunkOffset;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class ImportPkwtOneContracts implements ToCollection, WithHeadingRow, WithChunkReading, WithEvents, WithCalculatedFormulas, ShouldQueue
{
    use RegistersEventListeners;
    use RemembersChunkOffset;
    use TracksImportHistory;

    private string $signingMethod;
    private ?string $actorUserId;
    private ?string $actorName;

    public function __construct(
        ?int $importHistoryId = null,
        string $signingMethod = EmployeeContract::SIGNING_METHOD_ELECTRONIC,
        ?string $actorUserId = null,
        ?string $actorName = null
    ) {
        $this->importHistoryId = $importHistoryId;
        $this->signingMethod = $signingMethod;
        $this->actorUserId = $actorUserId;
        $this->actorName = $actorName;
    }

    public function collection(Collection $rows): void
    {
        $service = app(VhireOnboardingContractService::class);
        $processedNoKtp = [];
        $successCount = 0;
        $skippedCount = 0;
        $failedCount = 0;
        $insertedCount = 0;
        $updatedCount = 0;
        $failureSamples = [];
        $detailItems = [];

        foreach ($rows as $index => $row) {
            $rowNumber = (int) (isset($this->chunkOffset) ? $this->chunkOffset : 2) + (int) $index;
            $payload = $this->payloadFromRow($row);

            if ($payload['no_ktp'] === '' && $payload['nama'] === '') {
                continue;
            }

            $validationMessage = $this->validatePayload($payload, $processedNoKtp);

            if ($validationMessage !== null) {
                $skippedCount++;
                $this->addFailureSample($failureSamples, $rowNumber, $payload['no_ktp'], $validationMessage);
                $detailItems[] = [
                    'category' => ImportHistoryItem::CATEGORY_SKIPPED,
                    'row' => $rowNumber,
                    'nik' => $payload['no_ktp'],
                    'message' => $validationMessage,
                    'payload' => $payload,
                ];
                continue;
            }

            $processedNoKtp[] = $payload['no_ktp'];
            $alreadyExists = $this->candidateOrContractExists($payload);

            try {
                $service->importCandidateFromExcel($payload, $this->actorUserId, $this->actorName);
                $successCount++;

                if ($alreadyExists) {
                    $updatedCount++;
                    $detailItems[] = [
                        'category' => ImportHistoryItem::CATEGORY_UPDATED,
                        'row' => $rowNumber,
                        'nik' => $payload['no_ktp'],
                        'message' => 'Data kandidat/kontrak yang sudah ada diperbarui.',
                        'payload' => $payload,
                    ];
                } else {
                    $insertedCount++;
                }
            } catch (Throwable $exception) {
                $failedCount++;
                $this->addFailureSample($failureSamples, $rowNumber, $payload['no_ktp'], $exception->getMessage());
                $detailItems[] = [
                    'category' => ImportHistoryItem::CATEGORY_FAILED,
                    'row' => $rowNumber,
                    'nik' => $payload['no_ktp'],
                    'message' => $exception->getMessage(),
                    'payload' => $payload,
                ];
            }
        }

        $this->recordImportChunk(
            $rows->count(),
            $successCount,
            $skippedCount,
            $failedCount,
            $failureSamples,
            [
                'sync_target' => 'vhire',
                'signing_method' => $this->signingMethod,
                'chunk_processed_at' => now()->toIso8601String(),
            ],
            $insertedCount,
            $updatedCount,
            $detailItems
        );
    }

    public function chunkSize(): int
    {
        return 250;
    }

    public function headingRow(): int
    {
        return 1;
    }

    private function payloadFromRow($row): array
    {
        $noKtp = $this->digitsOnly($this->value($row, 'no_ktp'));
        $kodeKontrak = $this->nullableString($this->value($row, 'kode_kontrak'));
        $candidateCode = $kodeKontrak
            ? 'EXCEL-PKWT1-' . preg_replace('/[^A-Za-z0-9\-]+/', '-', $kodeKontrak)
            : 'EXCEL-PKWT1-' . substr(sha1($noKtp), 0, 12);
        $startDate = $this->parseDate($this->value($row, 'tanggal_mulai_kontrak'));
        $endDate = $this->parseDate($this->value($row, 'tanggal_berakhir_kontrak'));
        [$durationValue, $durationUnit] = $this->parseDuration($this->value($row, 'lama_kontrak'), $startDate, $endDate);

        return [
            'vhire_candidate_id' => null,
            'candidate_code' => $candidateCode,
            'no_ktp' => $noKtp,
            'nama' => trim($this->plainValue($this->value($row, 'nama'))),
            'jenis_kelamin' => $this->nullableString($this->value($row, 'jenis_kelamin')),
            'status_pernikahan' => $this->nullableString($this->value($row, 'status_pernikahan')),
            'alamat' => $this->nullableString($this->value($row, 'alamat')),
            'jabatan' => $this->nullableString($this->value($row, 'jabatan')),
            'tanggal_mulai_kerja' => optional($startDate)->format('Y-m-d'),
            'tanggal_akhir_kontrak' => optional($endDate)->format('Y-m-d'),
            'departemen' => null,
            'lokasi' => null,
            'kode_kontrak' => $kodeKontrak,
            'no_pkwt' => $this->nullableString($this->value($row, 'no_pkwt')),
            'gaji' => $this->parseMoney($this->value($row, 'gaji')),
            'uang_makan' => $this->parseMoney($this->value($row, 'uang_makan')),
            'recruitment_status' => 'excel_import',
            'onboarding_status' => OnboardingCandidate::STATUS_CONTRACT_GENERATED,
            'contract_duration_value' => $durationValue,
            'contract_duration_unit' => $durationUnit,
            'signing_method' => $this->signingMethod,
            'source_updated_at' => now()->format('Y-m-d H:i:s'),
        ];
    }

    private function validatePayload(array $payload, array $processedNoKtp): ?string
    {
        if (!preg_match('/^[0-9]{16}$/', $payload['no_ktp'])) {
            return 'No KTP wajib 16 digit angka.';
        }

        if (in_array($payload['no_ktp'], $processedNoKtp, true)) {
            return 'No KTP duplikat dalam chunk import.';
        }

        if ($payload['nama'] === '') {
            return 'Nama kandidat wajib diisi.';
        }

        if (!$payload['tanggal_mulai_kerja']) {
            return 'Tanggal mulai kontrak wajib diisi.';
        }

        if ((int) $payload['contract_duration_value'] < 1) {
            return 'Lama kontrak wajib diisi minimal 1.';
        }

        return null;
    }

    private function candidateOrContractExists(array $payload): bool
    {
        return OnboardingCandidate::query()
                ->where('candidate_code', $payload['candidate_code'])
                ->orWhere('no_ktp', $payload['no_ktp'])
                ->exists()
            || EmployeeContract::query()
                ->where('candidate_code', $payload['candidate_code'])
                ->orWhere('no_ktp', $payload['no_ktp'])
                ->exists();
    }

    private function value($row, string $key)
    {
        if (is_array($row)) {
            return $row[$key] ?? null;
        }

        if ($row instanceof Collection) {
            return $row->get($key);
        }

        return $row[$key] ?? null;
    }

    private function parseDate($value): ?Carbon
    {
        $value = $this->plainValue($value);

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_numeric($value) && (int) $value > 25000) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $normalizedDate = $this->normalizeIndonesianDateText($value);

        try {
            return Carbon::parse($normalizedDate);
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function parseDuration($value, ?Carbon $startDate, ?Carbon $endDate): array
    {
        $raw = Str::lower(trim((string) $value));
        $number = (int) preg_replace('/[^0-9]+/', '', $raw);

        if ($number < 1 && $startDate && $endDate) {
            $number = max(1, (int) $startDate->diffInMonths($endDate, true));
        }

        if (Str::contains($raw, ['hari', 'day'])) {
            return [$number ?: 1, 'day'];
        }

        if (Str::contains($raw, ['tahun', 'year'])) {
            return [$number ?: 1, 'year'];
        }

        return [$number ?: 1, 'month'];
    }

    private function parseMoney($value): ?float
    {
        $value = $this->plainValue($value);

        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = preg_replace('/[^0-9,\.\-]+/', '', (string) $value);

        if ($normalized === '' || $normalized === '-') {
            return null;
        }

        $lastComma = strrpos($normalized, ',');
        $lastDot = strrpos($normalized, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $normalized = $lastComma > $lastDot
                ? str_replace(',', '.', str_replace('.', '', $normalized))
                : str_replace(',', '', $normalized);
        } elseif ($lastComma !== false) {
            $normalized = preg_match('/,\d{1,2}$/', $normalized)
                ? str_replace(',', '.', $normalized)
                : str_replace(',', '', $normalized);
        } elseif ($lastDot !== false && !preg_match('/\.\d{1,2}$/', $normalized)) {
            $normalized = str_replace('.', '', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function digitsOnly($value): string
    {
        $value = $this->plainValue($value);

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return number_format($value, 0, '', '');
        }

        $value = trim((string) $value);

        if ($value !== '' && stripos($value, 'e') !== false && is_numeric($value)) {
            return number_format((float) $value, 0, '', '');
        }

        return preg_replace('/\D+/', '', $value) ?: '';
    }

    private function nullableString($value): ?string
    {
        $value = trim($this->plainValue($value));

        return $value === '' ? null : $value;
    }

    private function plainValue($value)
    {
        if ($value instanceof RichText) {
            return $value->getPlainText();
        }

        return $value;
    }

    private function normalizeIndonesianDateText(string $value): string
    {
        $months = [
            'januari' => 'January',
            'februari' => 'February',
            'maret' => 'March',
            'april' => 'April',
            'mei' => 'May',
            'juni' => 'June',
            'juli' => 'July',
            'agustus' => 'August',
            'september' => 'September',
            'oktober' => 'October',
            'november' => 'November',
            'desember' => 'December',
        ];

        return str_ireplace(array_keys($months), array_values($months), $value);
    }

    private function addFailureSample(array &$failureSamples, int $row, ?string $noKtp, string $message): void
    {
        if (count($failureSamples) >= 10) {
            return;
        }

        $failureSamples[] = [
            'status' => 'skip',
            'row' => $row,
            'nik' => $noKtp ? substr($noKtp, 0, 4) . '********' . substr($noKtp, -4) : null,
            'message' => Str::limit($message, 220, ''),
        ];
    }
}
