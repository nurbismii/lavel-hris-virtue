<?php

namespace App\Services\ElectronicContracts;

use App\Models\ContractClause;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateAsset;
use App\Models\ElectronicContractFirstPartySignature;
use App\Models\EmployeeContract;
use App\Models\EmployeeContractSignature;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ElectronicContractService
{
    private ContractHtmlSanitizer $sanitizer;

    public function __construct(ContractHtmlSanitizer $sanitizer)
    {
        $this->sanitizer = $sanitizer;
    }

    public function variableLabels(): array
    {
        return [
            'nik' => 'NIK',
            'no_ktp' => 'Nomor KTP',
            'nama_karyawan' => 'Nama karyawan',
            'jabatan' => 'Jabatan',
            'alamat' => 'Alamat',
            'jenis_kelamin' => 'Jenis kelamin',
            'status_pernikahan' => 'Status pernikahan',
            'no_kontrak' => 'Nomor kontrak',
            'kode_kontrak' => 'Kode kontrak',
            'no_pkwt' => 'Nomor PKWT',
            'durasi_kontrak' => 'Durasi kontrak',
            'tanggal_mulai_kontrak' => 'Tanggal mulai kontrak',
            'tanggal_berakhir_kontrak' => 'Tanggal berakhir kontrak',
            'gaji' => 'Gaji / upah terbaru',
            'uang_makan' => 'Uang makan',
            'durasi_perpanjangan_pertama' => 'Durasi perpanjangan pertama',
            'tanggal_perpanjangan_pertama_berakhir' => 'Tanggal perpanjangan pertama berakhir',
            'nomor_adendum' => 'Nomor adendum',
            'klausul' => 'Isi klausul terpilih',
            'klausul_formula' => 'Kalimat klausul formula Excel',
            'tanda_tangan_karyawan' => 'Slot tanda tangan karyawan',
            'tanda_tangan_pihak_kedua' => 'Slot tanda tangan Pihak Kedua',
            'tanda_tangan_pihak_pertama' => 'Slot tanda tangan Pihak Pertama',
            'tanda_tangan_penanda_tangan' => 'Slot tanda tangan penanda tangan',
        ];
    }

    public function createContract(array $data, User $actor): EmployeeContract
    {
        return DB::transaction(function () use ($data, $actor) {
            $template = ContractTemplate::query()
                ->where('id', $data['contract_template_id'])
                ->where('contract_type', $data['contract_type'])
                ->where('is_active', true)
                ->firstOrFail();

            $payload = [
                'nik' => $data['nik'],
                'contract_template_id' => $template->id,
                'contract_type' => $data['contract_type'],
                'status' => EmployeeContract::STATUS_READY,
                'contract_number' => $data['contract_number'] ?? null,
                'contract_code' => $data['contract_code'] ?? null,
                'pkwt_number' => $data['pkwt_number'],
                'gender' => $data['gender'] ?? null,
                'marital_status' => $data['marital_status'] ?? null,
                'address' => $data['address'] ?? null,
                'position' => $data['position'] ?? null,
                'contract_duration' => $data['contract_duration'] ?? null,
                'contract_start_date' => $data['contract_start_date'] ?? null,
                'contract_end_date' => $data['contract_end_date'] ?? null,
                'first_extension_duration' => $data['first_extension_duration'] ?? null,
                'first_extension_end_date' => $data['first_extension_end_date'] ?? null,
                'salary' => $data['salary'] ?? 0,
                'meal_allowance' => $data['meal_allowance'] ?? 0,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ];

            if ($data['contract_type'] === ContractTemplate::TYPE_ADDENDUM_PKWT) {
                $latestAddendum = EmployeeContract::query()
                    ->where('nik', $data['nik'])
                    ->where('contract_type', ContractTemplate::TYPE_ADDENDUM_PKWT)
                    ->whereNotNull('addendum_sequence')
                    ->orderByDesc('addendum_sequence')
                    ->lockForUpdate()
                    ->first(['addendum_sequence']);

                $sequence = ((int) optional($latestAddendum)->addendum_sequence) + 1;

                $payload['addendum_sequence'] = $sequence;
                $payload['addendum_number'] = $this->makeAddendumNumber(
                    $sequence,
                    $data['nik'],
                    $data['first_extension_end_date']
                );
                $payload['clause_key'] = $sequence === 1
                    ? ($data['clause_key'] ?? ContractClause::KEY_CLAUSE_2)
                    : ContractClause::KEY_CLAUSE_2;
            }

            $contract = EmployeeContract::create($payload);
            $contract->rendered_html = $this->renderContractHtml($contract->fresh(['template', 'employee']));
            $contract->save();

            return $contract;
        });
    }

    public function updateRenderedHtml(EmployeeContract $contract): EmployeeContract
    {
        $contract->loadMissing(['template', 'employee']);
        $contract->rendered_html = $this->renderContractHtml($contract);
        $contract->save();

        return $contract;
    }

    public function renderContractHtml(EmployeeContract $contract): string
    {
        $contract->loadMissing(['template', 'employee']);
        $template = $contract->template;
        $variables = $this->buildVariables($contract);
        $htmlVariables = $this->signatureHtmlVariables(null, null, false);

        if ($contract->isAddendum()) {
            $clause = ContractClause::query()
                ->where('clause_key', $contract->clause_key)
                ->where('is_active', true)
                ->first();
            $clauseHtml = $clause
                ? $this->replaceVariables($clause->body_html, $variables)
                : e($variables['klausul_formula']);

            $htmlVariables['klausul'] = $clauseHtml;
            $variables['klausul'] = strip_tags($clauseHtml);
        }

        $letterhead = $this->replaceVariables($template->letterhead_html ?: '', $variables, $htmlVariables);
        $body = $this->replaceVariables($template->body_html, $variables, $htmlVariables);

        if ($contract->isAddendum() && !Str::contains($template->body_html, '{{klausul')) {
            $body .= '<div class="contract-clause">' . ($htmlVariables['klausul'] ?? '') . '</div>';
        }

        return trim(
            '<div class="contract-letterhead">' . $letterhead . '</div>' .
            '<div class="contract-body">' . $body . '</div>'
        );
    }

    public function renderContractHtmlForDisplay(EmployeeContract $contract, ?EmployeeContractSignature $signature = null): string
    {
        $contract->loadMissing('employee');
        $html = $contract->rendered_html ?: $this->renderContractHtml($contract);

        return $this->embedSignaturesIntoHtml($html, $contract, $signature, false);
    }

    public function renderContractHtmlForPdf(EmployeeContract $contract, ?EmployeeContractSignature $signature = null): string
    {
        $contract->loadMissing('employee');
        $html = $contract->rendered_html ?: $this->renderContractHtml($contract);
        $html = $this->embedSignaturesIntoHtml($html, $contract, $signature, true);

        return $this->replaceAssetUrlsWithLocalPaths(
            $html
        );
    }

    public function generateSignedPdf(EmployeeContract $contract, EmployeeContractSignature $signature): EmployeeContract
    {
        $contract = $this->ensureFirstPartySignatureSnapshot($contract);
        $contract->loadMissing(['employee', 'template']);

        $pdf = Pdf::loadView('contracts.pdf', [
            'contract' => $contract,
            'html' => $this->renderContractHtmlForPdf($contract, $signature),
            'signature' => $signature,
        ])->setPaper('A4', 'portrait');

        $path = sprintf(
            'employee-contracts/%s/%s/signed-%s.pdf',
            $contract->nik,
            $contract->id,
            Str::uuid()
        );
        $oldPdfPath = $contract->pdf_path;
        $content = $pdf->output();

        Storage::put($path, $content);

        $contract->update([
            'pdf_path' => $path,
            'pdf_hash' => hash('sha256', $content),
            'status' => EmployeeContract::STATUS_SIGNED,
            'signed_at' => $signature->signed_at,
        ]);

        $signature->update([
            'document_hash' => $contract->pdf_hash,
        ]);

        if ($oldPdfPath && $oldPdfPath !== $path && Storage::exists($oldPdfPath)) {
            Storage::delete($oldPdfPath);
        }

        return $contract;
    }

    public function saveSignatureImage(string $signatureData, EmployeeContract $contract): string
    {
        return $this->saveSignatureDataImage($signatureData, $contract, 'employee');
    }

    public function saveFirstPartySignatureImage(string $signatureData, EmployeeContract $contract): string
    {
        return $this->saveSignatureDataImage($signatureData, $contract, 'first-party');
    }

    public function saveMasterFirstPartySignatureImage(string $signatureData): string
    {
        return $this->saveSignatureDataImage($signatureData, null, 'first-party');
    }

    public function saveFirstPartySignatureUpload(UploadedFile $file, EmployeeContract $contract): string
    {
        return $this->saveFirstPartySignatureUploadedFile($file, $contract);
    }

    public function saveMasterFirstPartySignatureUpload(UploadedFile $file): string
    {
        return $this->saveFirstPartySignatureUploadedFile($file, null);
    }

    public function saveMasterFirstPartySignature(string $signaturePath, string $source, User $actor): ElectronicContractFirstPartySignature
    {
        $signature = ElectronicContractFirstPartySignature::query()
            ->firstOrNew(['signer_key' => ElectronicContractFirstPartySignature::SIGNER_KEY]);

        $signature->fill([
            'signer_name' => $signature->signer_name ?: 'AHMAD SAEKUZEN',
            'signer_position' => $signature->signer_position ?: 'HRD MANAGER',
            'signature_path' => $signaturePath,
            'signature_source' => $source,
            'updated_by_user_id' => $actor->id,
            'signed_at' => now(),
        ]);
        $signature->save();

        return $signature;
    }

    public function firstPartySignature(): ?ElectronicContractFirstPartySignature
    {
        return ElectronicContractFirstPartySignature::query()
            ->where('signer_key', ElectronicContractFirstPartySignature::SIGNER_KEY)
            ->first();
    }

    public function firstPartySignaturePathForContract(EmployeeContract $contract): ?string
    {
        if ($contract->first_party_signature_path) {
            return $contract->first_party_signature_path;
        }

        return optional($this->firstPartySignature())->signature_path;
    }

    public function ensureFirstPartySignatureSnapshot(EmployeeContract $contract): EmployeeContract
    {
        if ($contract->first_party_signature_path) {
            return $contract;
        }

        $masterSignature = $this->firstPartySignature();

        if (!$masterSignature || !$masterSignature->signature_path || !Storage::exists($masterSignature->signature_path)) {
            return $contract;
        }

        $extension = pathinfo($masterSignature->signature_path, PATHINFO_EXTENSION) ?: 'png';
        $snapshotPath = sprintf(
            'employee-contract-first-party-signatures/%s/%s/%s.%s',
            $contract->nik,
            $contract->id,
            Str::uuid(),
            strtolower($extension)
        );

        Storage::put($snapshotPath, Storage::get($masterSignature->signature_path));

        $contract->update([
            'first_party_signature_path' => $snapshotPath,
            'first_party_signature_source' => 'master',
            'first_party_signed_by_user_id' => $masterSignature->updated_by_user_id,
            'first_party_signed_at' => $masterSignature->signed_at ?: now(),
        ]);

        return $contract->fresh();
    }

    private function saveFirstPartySignatureUploadedFile(UploadedFile $file, ?EmployeeContract $contract = null): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            throw new \InvalidArgumentException('File tanda tangan harus berupa JPG atau PNG.');
        }

        $path = $contract
            ? sprintf(
                'employee-contract-first-party-signatures/%s/%s/%s.%s',
                $contract->nik,
                $contract->id,
                Str::uuid(),
                $extension === 'jpeg' ? 'jpg' : $extension
            )
            : sprintf(
                'employee-contract-first-party-signatures/master/%s.%s',
                Str::uuid(),
                $extension === 'jpeg' ? 'jpg' : $extension
            );

        Storage::put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    public function storedSignatureImageSrc(?string $path, bool $forPdf = false): ?string
    {
        if (!$path) {
            return null;
        }

        $fullPath = Storage::path($path);

        if (!is_file($fullPath)) {
            return null;
        }

        if ($forPdf) {
            return 'file:///' . ltrim(str_replace('\\', '/', $fullPath), '/');
        }

        $content = file_get_contents($fullPath);

        if ($content === false) {
            return null;
        }

        $mimeType = function_exists('mime_content_type')
            ? strtolower((string) mime_content_type($fullPath))
            : '';

        if (!in_array($mimeType, ['image/png', 'image/jpeg'], true)) {
            $mimeType = Str::endsWith(strtolower($path), '.png') ? 'image/png' : 'image/jpeg';
        }

        return 'data:' . $mimeType . ';base64,' . base64_encode($content);
    }

    private function saveSignatureDataImage(string $signatureData, ?EmployeeContract $contract, string $party): string
    {
        if (!preg_match('/^data:image\/png;base64,/', $signatureData)) {
            throw new \InvalidArgumentException('Format tanda tangan tidak valid.');
        }

        $decoded = base64_decode(substr($signatureData, strpos($signatureData, ',') + 1), true);

        if ($decoded === false || strlen($decoded) < 100) {
            throw new \InvalidArgumentException('Data tanda tangan tidak dapat dibaca.');
        }

        if (strlen($decoded) > 1024 * 1024) {
            throw new \InvalidArgumentException('Ukuran tanda tangan terlalu besar.');
        }

        $folder = $party === 'first-party'
            ? 'employee-contract-first-party-signatures'
            : 'employee-contract-signatures';

        $path = $contract
            ? sprintf(
                '%s/%s/%s/%s.png',
                $folder,
                $contract->nik,
                $contract->id,
                Str::uuid()
            )
            : sprintf(
                '%s/master/%s.png',
                $folder,
                Str::uuid()
            );

        Storage::put($path, $decoded);

        return $path;
    }

    public function makeAddendumNumber(int $sequence, string $nik, string $date): string
    {
        $carbon = Carbon::parse($date);

        return sprintf(
            'AD-%d/PKWT/%s/HRD-VDNI/%s/%s',
            $sequence,
            $nik,
            bulan_romawi((int) $carbon->format('n')),
            $carbon->format('Y')
        );
    }

    public function buildVariables(EmployeeContract $contract): array
    {
        $employee = $contract->employee;
        $gender = $contract->gender ?: optional($employee)->jenis_kelamin;
        $maritalStatus = $contract->marital_status ?: optional($employee)->status_pernikahan;
        $address = $contract->address ?: (optional($employee)->alamat_domisili ?: optional($employee)->alamat_ktp);
        $position = $contract->position ?: optional($employee)->posisi;

        $variables = [
            'nik' => $contract->nik,
            'no_ktp' => optional($employee)->no_ktp,
            'nama' => optional($employee)->nama_karyawan,
            'nama_karyawan' => optional($employee)->nama_karyawan,
            'jabatan' => $position,
            'alamat' => $address,
            'jenis_kelamin' => $this->genderLabel($gender),
            'status_pernikahan' => $maritalStatus,
            'no_kontrak' => $contract->contract_number,
            'nomor_kontrak' => $contract->contract_number,
            'kode_kontrak' => $contract->contract_code,
            'no_pkwt' => $contract->pkwt_number,
            'nomor_pkwt' => $contract->pkwt_number,
            'durasi_kontrak' => $contract->contract_duration,
            'tanggal_mulai_kontrak' => $this->formatDate($contract->contract_start_date),
            'tanggal_berakhir_kontrak' => $this->formatDate($contract->contract_end_date),
            'tanggal_pkwt_dimulai' => $this->formatDate($contract->contract_start_date),
            'tanggal_pkwt_berakhir' => $this->formatDate($contract->contract_end_date),
            'gaji' => $this->formatRupiah($contract->salary),
            'upah_terbaru' => $this->formatRupiah($contract->salary),
            'uang_makan' => $this->formatRupiah($contract->meal_allowance),
            'durasi_perpanjangan_pertama' => $contract->first_extension_duration,
            'tanggal_perpanjangan_pertama_berakhir' => $this->formatDate($contract->first_extension_end_date),
            'nomor_adendum' => $contract->addendum_number,
            'no_adendum' => $contract->addendum_number,
            'urutan_adendum' => $contract->addendum_sequence,
            'klausul_formula' => $this->buildAddendumFormulaClause($contract),
        ];

        foreach ([
            'nik',
            'no_ktp',
            'nama_karyawan',
            'jabatan',
            'alamat',
            'jenis_kelamin',
            'status_pernikahan',
        ] as $key) {
            $variables['employee.' . $key] = $variables[$key] ?? null;
        }

        foreach ($variables as $key => $value) {
            if (!Str::startsWith($key, 'employee.')) {
                $variables['contract.' . $key] = $value;
            }
        }

        return array_map(fn($value) => $value === null || $value === '' ? '-' : (string) $value, $variables);
    }

    public function cleanHtml(?string $html): string
    {
        return $this->sanitizer->clean($html);
    }

    private function replaceVariables(string $html, array $variables, array $htmlVariables = []): string
    {
        return preg_replace_callback('/{{\s*([a-zA-Z0-9_.]+)\s*}}/', function ($matches) use ($variables, $htmlVariables) {
            $key = $matches[1];

            if (array_key_exists($key, $htmlVariables)) {
                return $htmlVariables[$key];
            }

            return e($variables[$key] ?? $matches[0]);
        }, $html);
    }

    private function signatureHtmlVariables(
        ?EmployeeContractSignature $employeeSignature,
        ?EmployeeContract $contract,
        bool $forPdf
    ): array
    {
        $employeeSignatureHtml = $this->signatureSlotHtml(
            $employeeSignature ? $this->signatureImageSrc($employeeSignature, $forPdf) : null,
            'employee'
        );
        $firstPartySignatureHtml = $this->signatureSlotHtml(
            $contract ? $this->storedSignatureImageSrc($this->firstPartySignaturePathForContract($contract), $forPdf) : null,
            'first_party'
        );

        return [
            'tanda_tangan_karyawan' => $employeeSignatureHtml,
            'tanda_tangan_pihak_kedua' => $employeeSignatureHtml,
            'tanda_tangan_pihak_pertama' => $firstPartySignatureHtml,
            'tanda_tangan_penanda_tangan' => $employeeSignatureHtml,
        ];
    }

    private function embedSignaturesIntoHtml(
        string $html,
        EmployeeContract $contract,
        ?EmployeeContractSignature $employeeSignature,
        bool $forPdf
    ): string {
        $signatureVariables = $this->signatureHtmlVariables($employeeSignature, $contract, $forPdf);

        [$html, $employeeReplacementCount] = $this->replaceSignatureSlot(
            $html,
            'employee',
            $signatureVariables['tanda_tangan_pihak_kedua']
        );
        [$html, $firstPartyReplacementCount] = $this->replaceSignatureSlot(
            $html,
            'first_party',
            $signatureVariables['tanda_tangan_pihak_pertama']
        );

        foreach ($signatureVariables as $placeholder => $signatureHtml) {
            [$html, $placeholderCount] = $this->replaceSignaturePlaceholder($html, $placeholder, $signatureHtml);

            if ($placeholder === 'tanda_tangan_pihak_pertama') {
                $firstPartyReplacementCount += $placeholderCount;
            } else {
                $employeeReplacementCount += $placeholderCount;
            }
        }

        if ($employeeSignature && $employeeReplacementCount < 1) {
            $html = $this->injectSignatureBeforeName(
                $html,
                optional($contract->employee)->nama_karyawan,
                $signatureVariables['tanda_tangan_pihak_kedua']
            );
        }

        if ($this->firstPartySignaturePathForContract($contract) && $firstPartyReplacementCount < 1) {
            $html = $this->injectSignatureBeforeName(
                $html,
                $this->firstPartySignerName($contract),
                $signatureVariables['tanda_tangan_pihak_pertama']
            );
        }

        return $html;
    }

    private function replaceSignatureSlot(string $html, string $role, string $signatureHtml): array
    {
        $updatedHtml = preg_replace(
            '/<(?P<tag>span|div)\b(?=[^>]*data-contract-signature=["\']' . preg_quote($role, '/') . '["\'])(?=[^>]*contract-signature-slot)[^>]*>.*?<\/(?P=tag)>/is',
            $signatureHtml,
            $html,
            -1,
            $replacementCount
        );

        return [$updatedHtml, (int) $replacementCount];
    }

    private function replaceSignaturePlaceholder(string $html, string $placeholder, string $signatureHtml): array
    {
        $updatedHtml = preg_replace(
            '/{{\s*' . preg_quote($placeholder, '/') . '\s*}}/',
            $signatureHtml,
            $html,
            -1,
            $replacementCount
        );

        return [$updatedHtml, (int) $replacementCount];
    }

    private function injectSignatureBeforeName(string $html, ?string $signerName, string $signatureHtml): string
    {
        if (!$signerName) {
            return $html . '<div style="margin-top: 36px; text-align: center;">' . $signatureHtml . '</div>';
        }

        $escapedName = e($signerName);
        $strongPattern = '/<strong>\s*' . preg_quote($escapedName, '/') . '\s*<\/strong>/i';

        if (preg_match_all($strongPattern, $html, $matches, PREG_OFFSET_CAPTURE) && count($matches[0]) > 0) {
            $lastMatch = end($matches[0]);
            $insertAt = (int) $lastMatch[1];

            return $this->placeSignatureAtNamePosition($html, $insertAt, $signatureHtml);
        }

        $plainPattern = '/' . preg_quote($escapedName, '/') . '/i';

        if (preg_match_all($plainPattern, $html, $matches, PREG_OFFSET_CAPTURE) && count($matches[0]) > 0) {
            $lastMatch = end($matches[0]);
            $insertAt = (int) $lastMatch[1];

            return $this->placeSignatureAtNamePosition($html, $insertAt, $signatureHtml);
        }

        return $html . '<div style="margin-top: 36px; text-align: center;">' . $signatureHtml . '</div>';
    }

    private function placeSignatureAtNamePosition(string $html, int $insertAt, string $signatureHtml): string
    {
        $prefix = substr($html, 0, $insertAt);
        $suffix = substr($html, $insertAt);

        if (preg_match('/(?:\s*<br\s*\/?>\s*){2,}$/i', $prefix, $matches, PREG_OFFSET_CAPTURE)) {
            $blankSignatureStart = (int) $matches[0][1];

            return substr($html, 0, $blankSignatureStart) . $signatureHtml . $suffix;
        }

        return $prefix . $signatureHtml . $suffix;
    }

    private function firstPartySignerName(EmployeeContract $contract): string
    {
        return 'AHMAD SAEKUZEN';
    }

    private function signatureSlotHtml(?string $signatureSrc, string $role): string
    {
        $innerHtml = '&nbsp;';

        if ($signatureSrc) {
            $innerHtml = sprintf(
                '<img src="%s" alt="Tanda tangan elektronik" class="contract-signature-image" style="height: 76px; max-width: 220px; vertical-align: middle;">',
                htmlspecialchars($signatureSrc, ENT_QUOTES, 'UTF-8')
            );
        }

        return sprintf(
            '<div class="contract-signature-slot" data-contract-signature="%s" style="display: block; height: 86px; line-height: normal; margin: 4px 0; text-align: center;"><table class="contract-signature-box" style="border: 0; border-collapse: collapse; height: 86px; margin: 0; width: 100%%;"><tr><td style="border: 0; height: 86px; padding: 0; text-align: center; vertical-align: middle;">%s</td></tr></table></div>',
            htmlspecialchars($role, ENT_QUOTES, 'UTF-8'),
            $innerHtml
        );
    }

    private function signatureImageSrc(EmployeeContractSignature $signature, bool $forPdf): ?string
    {
        return $this->storedSignatureImageSrc($signature->signature_path, $forPdf);
    }

    private function buildAddendumFormulaClause(EmployeeContract $contract): string
    {
        $text = sprintf(
            'Perjanjian Kerja Waktu Tertentu Nomor %s tertanggal %s',
            $contract->pkwt_number,
            $this->formatDate($contract->contract_start_date)
        );

        if ((int) $contract->addendum_sequence !== 1) {
            $text .= sprintf(
                ' sebagaimana telah ditambahkan terakhir dengan Adendum Perjanjian Kerja Waktu Tertentu Nomor %s tanggal %s;',
                $contract->addendum_number,
                $this->formatDate($contract->first_extension_end_date)
            );
        }

        return $text;
    }

    private function replaceAssetUrlsWithLocalPaths(string $html): string
    {
        return preg_replace_callback(
            '/(<img[^>]+src=["\'])([^"\']*(?:\/admin\/kontrak-elektronik\/assets|\/kontrak-elektronik-assets)\/(\d+)[^"\']*)(["\'][^>]*>)/i',
            function ($matches) {
                $asset = ContractTemplateAsset::find($matches[3]);

                if (!$asset) {
                    return $matches[0];
                }

                $path = Storage::path($asset->path);

                if (!is_file($path)) {
                    return $matches[0];
                }

                $url = 'file:///' . ltrim(str_replace('\\', '/', $path), '/');

                return $matches[1] . $url . $matches[4];
            },
            $html
        );
    }

    private function formatDate($date): string
    {
        if (!$date) {
            return '-';
        }

        return Carbon::parse($date)
            ->locale('id')
            ->isoFormat('D MMMM Y');
    }

    private function formatRupiah($amount): string
    {
        return 'Rp ' . number_format((float) $amount, 0, ',', '.');
    }

    private function genderLabel(?string $gender): string
    {
        $gender = strtoupper((string) $gender);

        if ($gender === 'L' || $gender === 'M') {
            return 'Laki-laki';
        }

        if ($gender === 'P' || $gender === 'F') {
            return 'Perempuan';
        }

        return $gender ?: '-';
    }
}
