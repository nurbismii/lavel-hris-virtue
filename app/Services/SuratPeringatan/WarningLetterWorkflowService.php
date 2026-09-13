<?php

namespace App\Services\SuratPeringatan;

use App\Models\ElectronicContractFirstPartySignature;
use App\Models\Employee;
use App\Models\SuratPeringatan;
use App\Models\User;
use App\Models\WarningLetterRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class WarningLetterWorkflowService
{
    public function __construct(private WarningLetterVerificationService $verificationService)
    {
    }

    public function employee(User $user, string $nik): Employee
    {
        return $user->applyEmployeeScope(Employee::query())
            ->select('nik', 'nama_karyawan', 'departemen_id', 'divisi_id', 'posisi')
            ->with(['departemen:id,departemen', 'divisi:id,nama_divisi'])
            ->where('nik', $nik)->firstOrFail();
    }

    public function searchEmployees(User $user, string $keyword, int $limit = 20): Collection
    {
        $keyword = trim($keyword);
        $escapedKeyword = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword);
        $containsKeyword = '%' . $escapedKeyword . '%';
        $startsWithKeyword = $escapedKeyword . '%';

        return $user->applyEmployeeScope(Employee::query())
            ->select('nik', 'nama_karyawan', 'departemen_id', 'divisi_id', 'posisi')
            ->with(['departemen:id,departemen', 'divisi:id,nama_divisi'])
            ->where(function ($query) use ($containsKeyword) {
                $query->whereRaw("nik LIKE ? ESCAPE '!'", [$containsKeyword])
                    ->orWhereRaw("nama_karyawan LIKE ? ESCAPE '!'", [$containsKeyword]);
            })
            ->orderByRaw("CASE WHEN nik = ? THEN 0 WHEN nik LIKE ? ESCAPE '!' THEN 1 WHEN nama_karyawan LIKE ? ESCAPE '!' THEN 2 ELSE 3 END", [
                $keyword,
                $startsWithKeyword,
                $startsWithKeyword,
            ])
            ->orderBy('nama_karyawan')
            ->limit($limit)
            ->get()
            ->map(function (Employee $employee) {
                return $this->employeeData($employee);
            });
    }

    public function employeeData(Employee $employee): array
    {
        return ['nik' => $employee->nik, 'name' => $employee->nama_karyawan,
            'department' => optional($employee->departemen)->departemen ?: '-',
            'division' => optional($employee->divisi)->nama_divisi ?: '-',
            'position' => $employee->posisi ?: '-'];
    }

    public function masterSigner(): ?ElectronicContractFirstPartySignature
    {
        return ElectronicContractFirstPartySignature::where('signer_key', ElectronicContractFirstPartySignature::SIGNER_KEY)->first();
    }

    public function submit(array $data, User $actor): WarningLetterRequest
    {
        Gate::forUser($actor)->authorize('create', WarningLetterRequest::class);
        $employee = $this->employee($actor, $data['nik']);
        $letter = WarningLetterRequest::firstOrCreate(['submission_token' => $data['submission_token']], [
            'nik' => $employee->nik,
            'status' => WarningLetterRequest::PENDING,
            'level_sp' => $data['level_sp'], 'keterangan' => $data['keterangan'],
            'pelapor' => $data['pelapor'], 'hod_name' => $data['hod_name'],
            'tgl_mulai' => $data['tgl_mulai'],
            'tgl_berakhir' => Carbon::parse($data['tgl_mulai'])
                ->addMonthsNoOverflow(WarningLetterRequest::VALIDITY_MONTHS)->format('Y-m-d'),
            'employee_snapshot' => $this->employeeData($employee),
            'created_by' => (string) $actor->id, 'created_by_name' => $this->actorName($actor),
        ]);
        abort_unless((string) $letter->created_by === (string) $actor->id, 403);

        return $letter;
    }

    public function review(WarningLetterRequest $letter, array $data, User $actor): WarningLetterRequest
    {
        Gate::forUser($actor)->authorize('review', $letter);
        $signaturePath = null;
        try {
            return DB::transaction(function () use ($letter, $data, $actor, &$signaturePath) {
                $letter = WarningLetterRequest::whereKey($letter->id)->lockForUpdate()->firstOrFail();
                if ($letter->status !== WarningLetterRequest::PENDING) {
                    throw ValidationException::withMessages(['approval' => 'Pengajuan sudah diproses. Muat ulang halaman untuk melihat status terakhir.']);
                }
                $review = ['reviewed_by' => (string) $actor->id, 'reviewed_by_name' => $this->actorName($actor), 'reviewed_at' => now()];
                if ($data['decision'] === 'reject') {
                    $letter->update($review + ['status' => WarningLetterRequest::REJECTED, 'rejection_reason' => $data['reason']]);
                    return $letter;
                }

                $signer = $this->masterSigner();
                if (!$signer || !$signer->signer_name || !$signer->signer_position || !$signer->signature_path
                    || !Storage::exists($signer->signature_path)) {
                    throw ValidationException::withMessages(['approval' => 'Nama, jabatan, dan tanda tangan pihak pertama belum lengkap. Lengkapi master tanda tangan kontrak elektronik terlebih dahulu.']);
                }
                $signature = Storage::get($signer->signature_path);
                $imageInfo = @getimagesizefromstring($signature);
                if (!$imageInfo || !in_array($imageInfo['mime'], ['image/png', 'image/jpeg'], true) || strlen($signature) > 5 * 1024 * 1024) {
                    throw ValidationException::withMessages(['approval' => 'Tanda tangan harus berupa PNG/JPEG yang valid, maksimal 5 MB.']);
                }
                $signaturePath = 'private/warning-letters/signatures/' . Str::uuid() . ($imageInfo['mime'] === 'image/png' ? '.png' : '.jpg');
                if (!Storage::disk('local')->put($signaturePath, $signature)) {
                    throw new \RuntimeException('Unable to save warning letter signature snapshot.');
                }
                $number = app(WarningLetterNumberService::class)->next();
                $issuedAt = now();
                $letterNumber = $number . '/' . config('warning_letters.number_code') . '/' . bulan_romawi((int) $issuedAt->month) . '/' . $issuedAt->year;
                $report = SuratPeringatan::create([
                    'nik_karyawan' => $letter->nik, 'no_sp' => (string) $number,
                    'level_sp' => $letter->level_sp, 'keterangan' => $letter->keterangan,
                    'pelapor' => $letter->pelapor, 'tgl_mulai' => $letter->tgl_mulai->format('Y-m-d'),
                    'tgl_berakhir' => $letter->tgl_berakhir->format('Y-m-d'),
                ]);
                $snapshot = [
                    'employee' => $letter->employee_snapshot,
                    'number' => $letterNumber, 'issued_at' => $issuedAt->format('Y-m-d'),
                    'level' => $letter->level_sp, 'description' => $letter->keterangan,
                    'start' => $letter->tgl_mulai->format('Y-m-d'), 'end' => $letter->tgl_berakhir->format('Y-m-d'),
                    'reporter' => $letter->pelapor, 'hod_name' => $letter->hod_name,
                    'signer_name' => $signer->signer_name, 'signer_position' => $signer->signer_position,
                    'signature_path' => $signaturePath, 'signature_mime' => $imageInfo['mime'],
                    'place' => config('warning_letters.place'), 'company' => config('warning_letters.company'),
                    'footer' => config('warning_letters.footer'),
                    'template_version' => 3,
                ];
                $letter->update($review + [
                    'status' => WarningLetterRequest::APPROVED, 'sp_report_id' => $report->id,
                    'number_sequence' => $number, 'letter_number' => $letterNumber,
                    'letter_snapshot' => $snapshot,
                ] + $this->verificationService->credentials($snapshot));

                return $letter;
            });
        } catch (Throwable $exception) {
            if ($signaturePath) {
                Storage::disk('local')->delete($signaturePath);
            }
            throw $exception;
        }
    }

    private function actorName(User $actor): string
    {
        $name = trim((string) $actor->name);
        if ($name === '' && filled($actor->nik_karyawan)) {
            $employee = $actor->relationLoaded('employee')
                ? $actor->getRelation('employee')
                : $actor->employee()->select('nik', 'nama_karyawan')->first();
            $name = trim((string) optional($employee)->nama_karyawan);
        }

        return Str::limit($name !== '' ? $name : 'Akun ' . $actor->getKey(), 180, '');
    }

    public function pdf(WarningLetterRequest $letter, User $viewer)
    {
        Gate::forUser($viewer)->authorize('view', $letter);
        abort_unless($letter->status === WarningLetterRequest::APPROVED && $letter->letter_snapshot, 409, 'Surat belum disetujui untuk diterbitkan.');
        $letter = $this->verificationService->ensureCredentials($letter, $viewer);
        $snapshot = $letter->letter_snapshot;
        $qrCodeSrc = $this->verificationService->qrDataUri($letter);
        $verificationUrl = $this->verificationService->url($letter);
        $verificationCode = strtoupper(substr((string) $letter->verification_document_hash, 0, 16));

        $logoMarkSrc = $this->embeddedPngAsset(
            'assets/img/vdni-letter-logo-mark.png',
            'Logo surat peringatan tidak tersedia. Hubungi administrator.'
        );
        $watermarkRightPath = $this->pngAssetPath(
            'assets/img/vdni-letter-watermark-right.png',
            'Watermark surat peringatan tidak tersedia. Hubungi administrator.'
        );
        $watermarkLeftPath = $this->pngAssetPath(
            'assets/img/vdni-letter-watermark-left.png',
            'Watermark surat peringatan tidak tersedia. Hubungi administrator.'
        );

        $fontCache = storage_path('framework/cache/dompdf-warning-letters');
        File::ensureDirectoryExists($fontCache);

        $pdf = Pdf::loadView('admin.surat-peringatan.letter-pdf', compact(
            'snapshot',
            'qrCodeSrc',
            'verificationUrl',
            'verificationCode',
            'logoMarkSrc'
        ))
            ->setOptions([
                'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false,
                'isFontSubsettingEnabled' => true,
                'fontDir' => str_replace('\\', '/', $fontCache),
                'fontCache' => str_replace('\\', '/', $fontCache),
                'allowedProtocols' => ['file://' => ['rules' => []], 'data://' => ['rules' => []]],
            ], true)->setPaper('a4');
        $domPdf = $pdf->getDomPDF();
        $domPdf->setCallbacks([[
            'event' => 'end_document',
            'f' => static function ($pageNumber, $pageCount, $canvas) use ($watermarkRightPath, $watermarkLeftPath) {
                // Positions and dimensions are converted from the OOXML anchors in the Word reference.
                $canvas->image($watermarkRightPath, 506.25, 373, 86.25, 136.5);
                $canvas->image($watermarkLeftPath, 2.85, 595, 96.95, 121.25);
            },
        ]]);
        $fontMetrics = $domPdf->getFontMetrics();
        $fontMetrics->loadFontFamilies();

        return $pdf;
    }

    private function embeddedPngAsset(string $relativePath, string $errorMessage): string
    {
        $path = $this->pngAssetPath($relativePath, $errorMessage);

        return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
    }

    private function pngAssetPath(string $relativePath, string $errorMessage): string
    {
        $path = public_path($relativePath);
        abort_unless(is_file($path), 500, $errorMessage);

        return $path;
    }

}
