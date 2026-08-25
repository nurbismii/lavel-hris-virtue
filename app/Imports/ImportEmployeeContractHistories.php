<?php

namespace App\Imports;

use App\Imports\Concerns\TracksImportHistory;
use App\Models\ContractTemplate;
use App\Models\EmployeeContractHistory;
use App\Models\ImportHistoryItem;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\RemembersChunkOffset;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class ImportEmployeeContractHistories implements ToCollection, WithHeadingRow, WithChunkReading, WithEvents, WithCalculatedFormulas, ShouldQueue
{
    use RegistersEventListeners;
    use RemembersChunkOffset;
    use TracksImportHistory;

    private ?string $actorUserId;

    public function __construct(?int $importHistoryId = null, ?string $actorUserId = null)
    {
        $this->importHistoryId = $importHistoryId;
        $this->actorUserId = $actorUserId;
    }

    public function collection(Collection $rows): void
    {
        $successCount = 0;
        $skippedCount = 0;
        $failedCount = 0;
        $insertedCount = 0;
        $updatedCount = 0;
        $failureSamples = [];
        $detailItems = [];

        foreach ($rows as $index => $row) {
            $rowNumber = (int) (isset($this->chunkOffset) ? $this->chunkOffset : 2) + (int) $index;
            $payload = $this->payloadFromRow($row, $rowNumber);

            if ($payload['nik'] === '' && $payload['employee_name'] === '' && $payload['contract_number'] === '') {
                continue;
            }

            $validationMessage = $this->validatePayload($payload);

            if ($validationMessage !== null) {
                $skippedCount++;
                $this->addFailureSample($failureSamples, $rowNumber, $payload['nik'], $validationMessage);
                $detailItems[] = [
                    'category' => ImportHistoryItem::CATEGORY_SKIPPED,
                    'row' => $rowNumber,
                    'nik' => $payload['nik'],
                    'message' => $validationMessage,
                    'payload' => $payload,
                ];
                continue;
            }

            try {
                $history = EmployeeContractHistory::query()->firstOrNew([
                    'nik' => $payload['nik'],
                    'history_sequence' => $payload['history_sequence'],
                    'contract_number' => $payload['contract_number'],
                    'contract_end_date' => $payload['contract_end_date'],
                ]);

                $exists = $history->exists;
                $history->fill($payload);
                $history->save();

                $successCount++;

                if ($exists) {
                    $updatedCount++;
                    $detailItems[] = [
                        'category' => ImportHistoryItem::CATEGORY_UPDATED,
                        'row' => $rowNumber,
                        'nik' => $payload['nik'],
                        'message' => 'History kontrak yang sudah ada diperbarui.',
                        'payload' => $payload,
                    ];
                } else {
                    $insertedCount++;
                }
            } catch (Throwable $exception) {
                $failedCount++;
                $this->addFailureSample($failureSamples, $rowNumber, $payload['nik'], $exception->getMessage());
                $detailItems[] = [
                    'category' => ImportHistoryItem::CATEGORY_FAILED,
                    'row' => $rowNumber,
                    'nik' => $payload['nik'],
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
            ['source_format' => 'vertical_contract_history'],
            $insertedCount,
            $updatedCount,
            $detailItems
        );
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function headingRow(): int
    {
        return 1;
    }

    private function payloadFromRow($row, int $rowNumber): array
    {
        $nik = $this->digitsOnly($this->value($row, 'nik_baru'));
        $rawHistoryType = $this->nullableString($this->value($row, 'history_jenis')) ?: '-';
        $durationLabel = $this->nullableString($this->value($row, 'durasi_bulan'));
        $durationMonths = $this->parseDurationMonths($durationLabel);
        $entryDate = $this->parseDate($this->value($row, 'entry_date'));
        $contractEndDate = $this->parseDate($this->value($row, 'tanggal_akhir_kontrak'));

        return [
            'nik' => $nik,
            'employee_name' => $this->nullableString($this->value($row, 'employee_name')),
            'marital_status' => $this->nullableString($this->value($row, 'status_pernikahan')),
            'employee_status' => $this->nullableString($this->value($row, 'employee_status')),
            'contract_number' => $this->nullableString($this->value($row, 'nomor_kontrak')),
            'entry_date' => optional($entryDate)->format('Y-m-d'),
            'history_sequence' => max(0, (int) $this->plainValue($this->value($row, 'history_urutan'))),
            'history_type' => $this->normalizeHistoryType($rawHistoryType),
            'raw_history_type' => $rawHistoryType,
            'duration_months' => $durationMonths,
            'duration_label' => $durationLabel,
            'contract_end_date' => optional($contractEndDate)->format('Y-m-d'),
            'source_import_history_id' => $this->importHistoryId(),
            'source_row_number' => $rowNumber,
            'created_by' => $this->actorUserId,
        ];
    }

    private function validatePayload(array $payload): ?string
    {
        if ($payload['nik'] === '') {
            return 'NIK_BARU wajib diisi.';
        }

        if (strlen($payload['nik']) > 100) {
            return 'NIK_BARU terlalu panjang.';
        }

        if ($payload['raw_history_type'] === '-') {
            return 'HISTORY_JENIS wajib diisi.';
        }

        return null;
    }

    private function normalizeHistoryType(string $value): string
    {
        $normalized = Str::upper(trim($value));

        if (Str::contains($normalized, 'PKWT')) {
            return ContractTemplate::TYPE_PKWT_1;
        }

        if (Str::contains($normalized, 'ADENDUM')) {
            return ContractTemplate::TYPE_ADDENDUM_PKWT;
        }

        return EmployeeContractHistory::TYPE_OTHER;
    }

    private function parseDurationMonths(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (!preg_match('/^\d+$/', $value)) {
            return null;
        }

        $months = (int) $value;

        return $months > 0 && $months <= 120 ? $months : null;
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

        try {
            return Carbon::parse($value);
        } catch (Throwable $exception) {
            return null;
        }
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
        $value = trim((string) $this->plainValue($value));

        return $value === '' ? null : $value;
    }

    private function plainValue($value)
    {
        if ($value instanceof RichText) {
            return $value->getPlainText();
        }

        return $value;
    }

    private function addFailureSample(array &$failureSamples, int $row, ?string $nik, string $message): void
    {
        if (count($failureSamples) >= 10) {
            return;
        }

        $failureSamples[] = [
            'status' => 'skip',
            'row' => $row,
            'nik' => $nik,
            'message' => Str::limit($message, 220, ''),
        ];
    }
}
