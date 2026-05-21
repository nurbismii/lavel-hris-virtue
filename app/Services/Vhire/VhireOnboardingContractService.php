<?php

namespace App\Services\Vhire;

use App\Models\ContractTemplate;
use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\ElectronicContractAuditLog;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeContractSignature;
use App\Models\OnboardingCandidate;
use App\Models\Perusahaan;
use App\Models\VhireSyncLog;
use App\Services\ElectronicContracts\ElectronicContractService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VhireOnboardingContractService
{
    private ElectronicContractService $contractService;
    private VhirePayloadSanitizer $sanitizer;
    private VhireSyncService $syncService;

    public function __construct(
        ElectronicContractService $contractService,
        VhirePayloadSanitizer $sanitizer,
        VhireSyncService $syncService
    ) {
        $this->contractService = $contractService;
        $this->sanitizer = $sanitizer;
        $this->syncService = $syncService;
    }

    public function receiveCandidate(array $payload, Request $request): array
    {
        $normalized = $this->normalizeCandidatePayload($payload);

        $result = DB::transaction(function () use ($normalized, $request) {
            $candidate = $this->resolveCandidate($normalized);
            $candidate->fill($normalized);
            $candidate->last_synced_at = now();
            $candidate->save();

            $contract = $this->createOrUpdatePkwtOneContract($candidate);

            if ($candidate->employee_nik) {
                $this->syncEmployeeProfileFromContract(
                    $contract->fresh(['onboardingCandidate']),
                    $candidate->employee_nik,
                    $contract->contract_start_date ? Carbon::parse($contract->contract_start_date) : now(),
                    true
                );
            }

            $this->recordInboundSyncLog(
                VhireSyncLog::OPERATION_ONBOARDING_CANDIDATE,
                $request,
                $normalized,
                OnboardingCandidate::class,
                $candidate->id
            );

            $this->recordAudit($contract, 'vhire_onboarding_candidate_received', $request, [
                'vhire_candidate_id' => $candidate->vhire_candidate_id,
                'candidate_code' => $candidate->candidate_code,
                'no_ktp' => $this->sanitizer->maskNoKtp($candidate->no_ktp),
                'signing_method' => $candidate->signing_method,
            ]);

            return [
                'candidate' => $candidate->fresh(),
                'contract' => $contract->fresh(['onboardingCandidate']),
            ];
        });

        $this->syncService->queueContractSync($result['contract']);

        return $result;
    }

    public function importCandidateFromExcel(array $payload, ?string $actorUserId = null, ?string $actorName = null): array
    {
        $normalized = $this->normalizeCandidatePayload($payload);

        $result = DB::transaction(function () use ($normalized, $actorUserId, $actorName) {
            $candidate = $this->resolveCandidate($normalized);
            $candidate->fill($normalized);
            $candidate->last_synced_at = now();
            $candidate->save();

            $contract = $this->createOrUpdatePkwtOneContract($candidate);

            $this->recordAuditFromContext($contract, 'pkwt_excel_imported_for_vhire', [
                'actor_user_id' => $actorUserId,
                'actor_name' => $actorName ?: 'HRIS Excel Import',
                'metadata' => [
                    'candidate_code' => $candidate->candidate_code,
                    'no_ktp' => $this->sanitizer->maskNoKtp($candidate->no_ktp),
                    'signing_method' => $candidate->signing_method,
                ],
            ]);

            return [
                'candidate' => $candidate->fresh(),
                'contract' => $contract->fresh(['onboardingCandidate']),
            ];
        });

        $this->syncService->queueContractSync($result['contract']);

        return $result;
    }

    public function updateSignatureStatus(EmployeeContract $contractFromRoute, array $payload, Request $request): EmployeeContract
    {
        return DB::transaction(function () use ($contractFromRoute, $payload, $request) {
            $contract = $this->resolveContractForSignature($contractFromRoute, $payload);
            $this->assertContractCandidateMatches($contract, $payload);
            $this->syncCandidateProfileFromIntegrationPayload($contract, $payload);

            $signatureStatus = (string) $payload['signature_status'];
            $currentSnapshot = [
                'status' => $contract->status,
                'signature_status' => $contract->signature_status,
                'signed_at' => optional($contract->signed_at)->format('Y-m-d H:i:s'),
            ];

            if (
                $contract->signature_status === $signatureStatus
                && (string) $contract->signed_by_source === (string) ($payload['signed_by_source'] ?? 'vhire')
            ) {
                $this->storeVhireEmployeeSignatureIfPresent($contract, $payload, $request);

                $this->recordInboundSyncLog(
                    VhireSyncLog::OPERATION_SIGNATURE_CALLBACK,
                    $request,
                    $payload,
                    EmployeeContract::class,
                    $contract->id
                );

                return $contract;
            }

            $contract->signature_status = $signatureStatus;
            $contract->signed_by_source = $payload['signed_by_source'] ?? 'vhire';

            if ($signatureStatus === EmployeeContract::SIGNATURE_STATUS_SIGNED) {
                $contract->status = EmployeeContract::STATUS_SIGNED;
                $contract->signed_at = !empty($payload['signed_at']) ? Carbon::parse($payload['signed_at']) : now();
            } elseif ($signatureStatus === EmployeeContract::SIGNATURE_STATUS_REJECTED) {
                $contract->status = EmployeeContract::STATUS_REJECTED;
            } elseif ($signatureStatus === EmployeeContract::SIGNATURE_STATUS_CANCELLED) {
                $contract->status = EmployeeContract::STATUS_CANCELLED;
            }

            $contract->save();
            $signature = $this->storeVhireEmployeeSignatureIfPresent($contract, $payload, $request);

            $this->recordInboundSyncLog(
                VhireSyncLog::OPERATION_SIGNATURE_CALLBACK,
                $request,
                $payload,
                EmployeeContract::class,
                $contract->id
            );

            $this->recordAudit($contract, 'vhire_signature_status_received', $request, [
                'old' => $currentSnapshot,
                'new' => [
                    'status' => $contract->status,
                    'signature_status' => $contract->signature_status,
                    'signed_at' => optional($contract->signed_at)->format('Y-m-d H:i:s'),
                    'signed_by_source' => $contract->signed_by_source,
                    'signature_id' => optional($signature)->id,
                ],
            ]);

            return $contract->fresh();
        });
    }

    public function activateContract(EmployeeContract $contract, string $employeeNik, Request $request): OnboardingCandidate
    {
        $candidate = DB::transaction(function () use ($contract, $employeeNik, $request) {
            $contract = EmployeeContract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();

            return $this->activateLockedContract($contract, $employeeNik, $request);
        });

        $this->syncService->queueActivationSync($candidate, $request->user());

        return $candidate;
    }

    public function generateEmployeeNikAndActivateContract(EmployeeContract $contract, Request $request): Employee
    {
        $result = DB::transaction(function () use ($contract, $request) {
            $contract = EmployeeContract::query()
                ->whereKey($contract->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCanGenerateEmployeeNik($contract);

            $activeDate = $contract->contract_start_date
                ? Carbon::parse($contract->contract_start_date)
                : now();
            $employeeNik = $this->nextGeneratedEmployeeNik($activeDate);

            $employee = $this->syncEmployeeProfileFromContract(
                $contract->fresh(['onboardingCandidate']),
                $employeeNik,
                $activeDate,
                true
            );

            $candidate = $this->activateLockedContract(
                $contract,
                $employeeNik,
                $request,
                ['generated_employee_nik' => true]
            );

            return [
                'candidate' => $candidate,
                'employee' => $employee->fresh(),
            ];
        });

        $this->syncService->queueActivationSync($result['candidate'], $request->user());

        return $result['employee'];
    }

    public function bulkGenerateEmployeeNikAndActivateContracts(array $contractIds, Request $request): array
    {
        $contractIds = collect($contractIds)
            ->map(fn($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $contracts = EmployeeContract::query()
            ->with(['employee:nik,nama_karyawan', 'onboardingCandidate:id,nama'])
            ->whereIn('id', $contractIds)
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $result = [
            'success_count' => 0,
            'failed_count' => 0,
            'successes' => [],
            'failures' => [],
        ];

        foreach ($contractIds as $contractId) {
            $contract = $contracts->get($contractId);

            if (!$contract) {
                $result['failed_count']++;
                $result['failures'][] = [
                    'contract_id' => $contractId,
                    'name' => '-',
                    'message' => 'Kontrak tidak ditemukan.',
                ];
                continue;
            }

            try {
                $employee = $this->generateEmployeeNikAndActivateContract($contract, $request);
                $result['success_count']++;
                $result['successes'][] = [
                    'contract_id' => $contract->id,
                    'name' => $contract->display_employee_name,
                    'employee_nik' => $employee->nik,
                ];
            } catch (ValidationException $exception) {
                $result['failed_count']++;
                $result['failures'][] = [
                    'contract_id' => $contract->id,
                    'name' => $contract->display_employee_name,
                    'message' => $this->firstValidationMessage($exception),
                ];
            } catch (\Throwable $exception) {
                report($exception);

                $result['failed_count']++;
                $result['failures'][] = [
                    'contract_id' => $contract->id,
                    'name' => $contract->display_employee_name,
                    'message' => Str::limit($exception->getMessage(), 180),
                ];
            }
        }

        return $result;
    }

    private function activateLockedContract(
        EmployeeContract $contract,
        string $employeeNik,
        Request $request,
        array $auditMetadata = []
    ): OnboardingCandidate {
        if (!$contract->onboarding_candidate_id) {
            throw ValidationException::withMessages([
                'employee_nik' => 'Kontrak ini bukan kontrak onboarding/PKWT V-Hire.',
            ]);
        }

        $candidate = OnboardingCandidate::query()
            ->whereKey($contract->onboarding_candidate_id)
            ->lockForUpdate()
            ->firstOrFail();

        $candidate->update([
            'employee_nik' => $employeeNik,
            'onboarding_status' => OnboardingCandidate::STATUS_ACTIVATED,
            'activated_as_employee_at' => $candidate->activated_as_employee_at ?: now(),
        ]);

        EmployeeContract::query()
            ->where('onboarding_candidate_id', $candidate->id)
            ->update([
                'nik' => $employeeNik,
                'employee_nik' => $employeeNik,
                'visible_in_vhire' => false,
                'updated_by' => optional($request->user())->id,
                'updated_at' => now(),
            ]);

        $updatedContract = $contract->fresh(['template', 'employee']);
        $updatedContract->rendered_html = $this->contractService->renderContractHtml($updatedContract);
        $updatedContract->save();

        $activeDate = $updatedContract->contract_start_date
            ? Carbon::parse($updatedContract->contract_start_date)
            : now();
        $this->syncEmployeeProfileFromContract(
            $updatedContract->fresh(['onboardingCandidate']),
            $employeeNik,
            $activeDate,
            false
        );

        $this->recordAudit($updatedContract, 'vhire_candidate_activated_as_employee', $request, array_merge([
            'vhire_candidate_id' => $candidate->vhire_candidate_id,
            'candidate_code' => $candidate->candidate_code,
            'employee_nik' => $employeeNik,
        ], $auditMetadata));

        return $candidate->fresh();
    }

    private function assertCanGenerateEmployeeNik(EmployeeContract $contract): void
    {
        if ($contract->contract_type !== ContractTemplate::TYPE_PKWT_1) {
            throw ValidationException::withMessages([
                'employee_nik' => 'Generate NIK hanya tersedia untuk kontrak PKWT 1.',
            ]);
        }

        if ($contract->signature_status !== EmployeeContract::SIGNATURE_STATUS_SIGNED) {
            throw ValidationException::withMessages([
                'employee_nik' => 'Kontrak PKWT 1 harus sudah ditandatangani sebelum generate NIK.',
            ]);
        }

        if ($contract->nik || $contract->employee_nik) {
            throw ValidationException::withMessages([
                'employee_nik' => 'Kontrak ini sudah ditautkan ke NIK HRIS.',
            ]);
        }

        if (!$contract->onboarding_candidate_id) {
            throw ValidationException::withMessages([
                'employee_nik' => 'Kontrak ini bukan kontrak onboarding/PKWT V-Hire.',
            ]);
        }

        $noKtp = (string) ($contract->no_ktp ?: optional($contract->onboardingCandidate)->no_ktp);

        if (
            $noKtp !== ''
            && Employee::query()
            ->where('no_ktp', $noKtp)
            ->whereRaw('UPPER(TRIM(COALESCE(status_resign, ""))) = ?', ['AKTIF'])
            ->exists()
        ) {
            throw ValidationException::withMessages([
                'employee_nik' => 'No KTP kandidat masih terdaftar sebagai karyawan aktif. Pakai aktivasi ke NIK yang sudah ada.',
            ]);
        }
    }

    private function firstValidationMessage(ValidationException $exception): string
    {
        $errors = $exception->errors();
        $firstField = array_key_first($errors);

        if ($firstField && isset($errors[$firstField][0])) {
            return (string) $errors[$firstField][0];
        }

        return 'Kontrak tidak memenuhi syarat generate NIK.';
    }

    private function nextGeneratedEmployeeNik(Carbon $activeDate): string
    {
        $prefix = $activeDate->format('ym');

        if (Schema::hasTable('employee_nik_sequences')) {
            return $this->nextGeneratedEmployeeNikFromSequence($prefix);
        }

        return $this->nextGeneratedEmployeeNikFromEmployees($prefix);
    }

    private function nextGeneratedEmployeeNikFromSequence(string $prefix): string
    {
        $this->ensureEmployeeNikSequence($prefix);

        $sequence = DB::table('employee_nik_sequences')
            ->where('prefix', $prefix)
            ->lockForUpdate()
            ->first();

        $lastSuffix = (int) optional($sequence)->last_suffix;
        $suffixLength = max(5, strlen((string) max($lastSuffix, 0)));
        $nextSuffix = $lastSuffix + 2;

        if ($nextSuffix < 2) {
            $nextSuffix = 2;
        }

        do {
            $usedSuffix = $nextSuffix;
            $nik = $prefix . str_pad((string) $usedSuffix, $suffixLength, '0', STR_PAD_LEFT);
            $nextSuffix += 2;
        } while (Employee::query()->whereKey($nik)->exists());

        DB::table('employee_nik_sequences')
            ->where('prefix', $prefix)
            ->update([
                'last_suffix' => $usedSuffix,
                'updated_at' => now(),
            ]);

        return $nik;
    }

    private function ensureEmployeeNikSequence(string $prefix): void
    {
        if (DB::table('employee_nik_sequences')->where('prefix', $prefix)->exists()) {
            return;
        }

        $maxSuffix = $this->largestExistingEmployeeNikSuffix($prefix);

        DB::table('employee_nik_sequences')->insertOrIgnore([
            'prefix' => $prefix,
            'last_suffix' => $maxSuffix,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function nextGeneratedEmployeeNikFromEmployees(string $prefix): string
    {
        $largestSuffix = $this->largestExistingEmployeeNikSuffix($prefix, true);

        $suffixLength = 5;
        $nextSuffix = 2;

        if ($largestSuffix > 0) {
            $suffixLength = max($suffixLength, strlen((string) $largestSuffix));
            $nextSuffix = $largestSuffix + 2;
        }

        do {
            $nik = $prefix . str_pad((string) $nextSuffix, $suffixLength, '0', STR_PAD_LEFT);
            $nextSuffix += 2;
        } while (Employee::query()->whereKey($nik)->exists());

        return $nik;
    }

    private function largestExistingEmployeeNikSuffix(string $prefix, bool $lockRows = false): int
    {
        $query = Employee::query()
            ->where('nik', 'like', $prefix . '%');

        if ($lockRows) {
            $query->lockForUpdate();
        }

        return $query
            ->pluck('nik')
            ->map(fn($nik) => (string) $nik)
            ->filter(fn(string $nik) => ctype_digit($nik) && Str::startsWith($nik, $prefix) && strlen($nik) > strlen($prefix))
            ->map(fn(string $nik) => (int) substr($nik, strlen($prefix)))
            ->max() ?: 0;
    }

    private function syncEmployeeProfileFromContract(
        EmployeeContract $contract,
        string $employeeNik,
        Carbon $activeDate,
        bool $overwriteExisting
    ): Employee {
        $attributes = $this->employeeAttributesFromContract($contract, $employeeNik, $activeDate);
        $employee = Employee::query()->whereKey($employeeNik)->lockForUpdate()->first();

        if (!$employee) {
            $this->validateEmployeeImportRequiredAttributes($attributes);

            return Employee::create($attributes);
        }

        $updates = $overwriteExisting
            ? $attributes
            : collect($attributes)
            ->reject(fn($value, string $column) => $column === 'nik' || $value === null || $value === '')
            ->filter(fn($value, string $column) => blank($employee->{$column}))
            ->all();

        if ($updates) {
            $employee->fill($updates);
            $employee->save();
        }

        return $employee->fresh();
    }

    private function employeeAttributesFromContract(EmployeeContract $contract, string $employeeNik, Carbon $activeDate): array
    {
        $candidate = $contract->onboardingCandidate;
        $kodeAreaKerja = optional($candidate)->kode_area_kerja ?: '02';
        $departemenName = $contract->departemen ?: optional($candidate)->departemen;
        $divisiName = optional($candidate)->divisi;
        $areaKerja = $this->employeeAreaKerja($contract, $candidate, $kodeAreaKerja);
        $departemenId = $this->verifiedReferenceId('departemens', optional($candidate)->departemen_id)
            ?: $this->resolveDepartemenId($areaKerja, $departemenName);
        $divisiId = $this->verifiedReferenceId('divisis', optional($candidate)->divisi_id)
            ?: $this->resolveDivisiId($divisiName, $departemenId);
        $departemenId = $departemenId ?: $this->departemenIdFromDivisi($divisiId);

        $attributes = [
            'nik' => $employeeNik,
            'nama_karyawan' => $contract->display_employee_name !== '-' ? $contract->display_employee_name : ($contract->candidate_name ?: optional($candidate)->nama),
            'nama_ibu_kandung' => optional($candidate)->nama_ibu_kandung,
            'nama_bapak' => optional($candidate)->nama_bapak,
            'agama' => optional($candidate)->agama,
            'no_ktp' => $contract->no_ktp ?: optional($candidate)->no_ktp,
            'no_kk' => optional($candidate)->no_kk,
            'kode_area_kerja' => $kodeAreaKerja,
            'posisi' => $contract->position,
            'jabatan' => optional($candidate)->jabatan ?: $contract->position,
            'jenis_kelamin' => $this->employeeImportGender($contract->gender ?: optional($candidate)->jenis_kelamin),
            'status_perkawinan' => $this->employeeImportMaritalStatus(optional($candidate)->status_pernikahan),
            'status_karyawan' => optional($candidate)->status_karyawan ?: 'PKWT 合同工',
            'no_telp' => optional($candidate)->no_telp,
            'tgl_lahir' => optional($candidate)->tanggal_lahir ? Carbon::parse($candidate->tanggal_lahir)->toDateString() : null,
            'alamat_domisili' => optional($candidate)->alamat_domisili ?: ($contract->address ?: optional($candidate)->alamat),
            'alamat_ktp' => optional($candidate)->alamat_ktp ?: ($contract->address ?: optional($candidate)->alamat),
            'provinsi_id' => $this->verifiedReferenceId('master_provinsi', optional($candidate)->provinsi_id),
            'kabupaten_id' => $this->verifiedReferenceId('master_kabupaten', optional($candidate)->kabupaten_id),
            'kecamatan_id' => $this->verifiedReferenceId('master_kecamatan', optional($candidate)->kecamatan_id),
            'kelurahan_id' => $this->verifiedReferenceId('master_kelurahan', optional($candidate)->kelurahan_id),
            'rt' => optional($candidate)->rt,
            'rw' => optional($candidate)->rw,
            'kode_pos' => optional($candidate)->kode_pos,
            'golongan_darah' => optional($candidate)->golongan_darah,
            'entry_date' => $activeDate->toDateString(),
            'npwp' => filled(optional($candidate)->npwp) ? preg_replace('/[^\d]/', '', (string) $candidate->npwp) : null,
            'status_pajak' => optional($candidate)->status_pajak,
            'bpjs_kesehatan' => optional($candidate)->bpjs_kesehatan,
            'bpjs_tk' => optional($candidate)->bpjs_tk,
            'jam_kerja' => optional($candidate)->jam_kerja,
            'status_resign' => 'AKTIF',
            'area_kerja' => $areaKerja,
            'departemen_id' => $departemenId,
            'divisi_id' => $divisiId,
            'skill' => optional($candidate)->skill,
            'tinggi' => optional($candidate)->tinggi,
            'berat' => optional($candidate)->berat,
            'hobi' => optional($candidate)->hobi,
            'no_jamsostek' => optional($candidate)->no_jamsostek,
            'no_asuransi' => optional($candidate)->no_asuransi,
            'no_kartu_asuransi' => optional($candidate)->no_kartu_asuransi,
            'nama_bank' => optional($candidate)->nama_bank,
            'no_rekening' => optional($candidate)->no_rekening,
            'nama_instansi_pendidikan' => optional($candidate)->nama_instansi_pendidikan,
            'pendidikan_terakhir' => optional($candidate)->pendidikan_terakhir,
            'jurusan' => optional($candidate)->jurusan,
            'tanggal_menikah' => optional($candidate)->tanggal_menikah ? Carbon::parse($candidate->tanggal_menikah)->toDateString() : null,
            'sisa_cuti' => optional($candidate)->sisa_cuti,
            'sisa_cuti_covid' => optional($candidate)->sisa_cuti_covid,
        ];

        return collect($attributes)
            ->filter(fn($value, string $column) => Schema::hasColumn('employees', $column) && $value !== null)
            ->all();
    }

    private function validateEmployeeImportRequiredAttributes(array $attributes): void
    {
        $messages = [];

        if (blank($attributes['nik'] ?? null)) {
            $messages['nik'] = 'NIK karyawan harus diisi';
        }

        if (blank($attributes['status_resign'] ?? null)) {
            $messages['status_resign'] = 'Status resign harus diisi';
        }

        if (blank($attributes['kode_area_kerja'] ?? null)) {
            $messages['kode_area_kerja'] = 'Kode area kerja harus diisi';
        }

        if ($messages) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function employeeImportGender(?string $value): ?string
    {
        $normalized = strtoupper(trim((string) $value));

        if (in_array($normalized, ['L', 'LAKI-LAKI', 'LAKI LAKI', 'M', 'MALE'], true)) {
            return 'L';
        }

        if (in_array($normalized, ['P', 'PEREMPUAN', 'WANITA', 'F', 'FEMALE'], true)) {
            return 'P';
        }

        return $this->nullableString($value);
    }

    private function employeeImportMaritalStatus(?string $value): ?string
    {
        $normalized = strtoupper(trim((string) $value));

        if (in_array($normalized, ['TK', 'BELUM KAWIN', 'BELUM MENIKAH', 'SINGLE'], true)) {
            return 'Belum Kawin';
        }

        if (in_array($normalized, ['K', 'KAWIN', 'MENIKAH', 'MARRIED'], true)) {
            return 'Kawin';
        }

        if (str_contains($normalized, 'CERAI')) {
            return 'Cerai';
        }

        return $this->nullableString($value);
    }

    private function employeeAreaKerja(EmployeeContract $contract, ?OnboardingCandidate $candidate, ?string $kodeAreaKerja): string
    {
        foreach ([
            optional($candidate)->area_kerja ?? null,
            optional($candidate)->lokasi ?? null,
            $contract->lokasi,
            $kodeAreaKerja,
            'VDNI',
        ] as $value) {
            $value = $this->nullableString($value);

            if ($value !== null && $this->perusahaanExists($value)) {
                return $value;
            }
        }

        return 'VDNI';
    }

    private function resolveDepartemenId(?string $areaKerja, ?string $departemen): ?int
    {
        if (blank($areaKerja) || blank($departemen) || !Schema::hasTable('perusahaan') || !Schema::hasTable('departemens')) {
            return null;
        }

        $perusahaanId = Perusahaan::query()
            ->whereRaw('LOWER(TRIM(kode_perusahaan)) = ?', [strtolower(trim((string) $areaKerja))])
            ->value('id');

        if (!$perusahaanId) {
            return null;
        }

        $departemenId = Departemen::query()
            ->where('perusahaan_id', $perusahaanId)
            ->whereRaw('LOWER(TRIM(departemen)) = ?', [strtolower(trim((string) $departemen))])
            ->value('id');

        return $departemenId ? (int) $departemenId : null;
    }

    private function resolveDivisiId(?string $divisi, ?int $departemenId = null): ?int
    {
        if (blank($divisi) || !Schema::hasTable('divisis')) {
            return null;
        }

        $query = Divisi::query()
            ->whereRaw('LOWER(TRIM(nama_divisi)) = ?', [strtolower(trim((string) $divisi))]);

        if ($departemenId) {
            $query->where('departemen_id', $departemenId);
        }

        $divisiId = $query->value('id');

        return $divisiId ? (int) $divisiId : null;
    }

    private function departemenIdFromDivisi(?int $divisiId): ?int
    {
        if (!$divisiId || !Schema::hasTable('divisis')) {
            return null;
        }

        $departemenId = Divisi::query()->whereKey($divisiId)->value('departemen_id');

        return $departemenId ? (int) $departemenId : null;
    }

    private function verifiedReferenceId(string $table, $id): ?int
    {
        $id = $this->nullableInteger($id);

        if (!$id) {
            return null;
        }

        if (!Schema::hasTable($table)) {
            return $id;
        }

        return DB::table($table)->where('id', $id)->exists() ? $id : null;
    }

    private function perusahaanExists(string $kodePerusahaan): bool
    {
        if (!Schema::hasTable('perusahaan')) {
            return false;
        }

        return Perusahaan::query()
            ->whereRaw('LOWER(TRIM(kode_perusahaan)) = ?', [strtolower(trim($kodePerusahaan))])
            ->exists();
    }

    private function normalizeCandidatePayload(array $payload): array
    {
        $durationUnit = $this->normalizeDurationUnit((string) $payload['contract_duration_unit']);
        $normalized = [
            'vhire_candidate_id' => $this->nullableString($payload['vhire_candidate_id'] ?? null),
            'candidate_code' => trim((string) $payload['candidate_code']),
            'no_ktp' => preg_replace('/\D+/', '', (string) $payload['no_ktp']),
            'nama' => trim((string) $payload['nama']),
            'jenis_kelamin' => $this->nullableString($payload['jenis_kelamin'] ?? null),
            'status_pernikahan' => $this->nullableString($payload['status_pernikahan'] ?? null),
            'alamat' => $this->nullableString($payload['alamat'] ?? null),
            'alamat_ktp' => $this->nullableString($payload['alamat_ktp'] ?? null),
            'alamat_domisili' => $this->nullableString($payload['alamat_domisili'] ?? null),
            'jabatan' => $this->nullableString($payload['jabatan'] ?? null),
            'tanggal_mulai_kerja' => !empty($payload['tanggal_mulai_kerja'])
                ? Carbon::parse($payload['tanggal_mulai_kerja'])->format('Y-m-d')
                : null,
            'tanggal_akhir_kontrak' => !empty($payload['tanggal_akhir_kontrak'])
                ? Carbon::parse($payload['tanggal_akhir_kontrak'])->format('Y-m-d')
                : null,
            'departemen' => $this->nullableString($payload['departemen'] ?? null),
            'departemen_id' => $this->nullableInteger($payload['departemen_id'] ?? null),
            'divisi' => $this->nullableString($payload['divisi'] ?? null),
            'divisi_id' => $this->nullableInteger($payload['divisi_id'] ?? null),
            'lokasi' => $this->nullableString($payload['lokasi'] ?? null),
            'kode_kontrak' => $this->nullableString($payload['kode_kontrak'] ?? null),
            'no_pkwt' => $this->nullableString($payload['no_pkwt'] ?? null),
            'gaji' => isset($payload['gaji']) && $payload['gaji'] !== '' ? (float) $payload['gaji'] : null,
            'uang_makan' => isset($payload['uang_makan']) && $payload['uang_makan'] !== '' ? (float) $payload['uang_makan'] : null,
            'recruitment_status' => $this->nullableString($payload['recruitment_status'] ?? null),
            'onboarding_status' => $this->nullableString($payload['onboarding_status'] ?? null) ?: OnboardingCandidate::STATUS_PENDING,
            'contract_duration_value' => (int) $payload['contract_duration_value'],
            'contract_duration_unit' => $durationUnit,
            'signing_method' => (string) $payload['signing_method'],
            'source_updated_at' => !empty($payload['source_updated_at']) ? Carbon::parse($payload['source_updated_at']) : null,
            'nama_ibu_kandung' => $this->nullableString($payload['nama_ibu_kandung'] ?? null),
            'nama_bapak' => $this->nullableString($payload['nama_bapak'] ?? $payload['nama_suami_atau_istri'] ?? null),
            'agama' => $this->nullableString($payload['agama'] ?? null),
            'no_kk' => preg_replace('/\D+/', '', (string) ($payload['no_kk'] ?? '')),
            'kode_area_kerja' => $this->nullableString($payload['kode_area_kerja'] ?? null),
            'status_karyawan' => $this->nullableString($payload['status_karyawan'] ?? null),
            'no_telp' => $this->nullableString($payload['no_telp'] ?? null),
            'tanggal_lahir' => !empty($payload['tanggal_lahir'] ?? $payload['tgl_lahir'] ?? null)
                ? Carbon::parse($payload['tanggal_lahir'] ?? $payload['tgl_lahir'])->format('Y-m-d')
                : null,
            'provinsi_id' => $this->nullableInteger($payload['provinsi_id'] ?? null),
            'kabupaten_id' => $this->nullableInteger($payload['kabupaten_id'] ?? null),
            'kecamatan_id' => $this->nullableInteger($payload['kecamatan_id'] ?? null),
            'kelurahan_id' => $this->nullableInteger($payload['kelurahan_id'] ?? null),
            'rt' => $this->nullableString($payload['rt'] ?? null),
            'rw' => $this->nullableString($payload['rw'] ?? null),
            'kode_pos' => $this->nullableString($payload['kode_pos'] ?? null),
            'golongan_darah' => $this->nullableString($payload['golongan_darah'] ?? null),
            'npwp' => preg_replace('/[^\d]/', '', (string) ($payload['npwp'] ?? '')),
            'status_pajak' => $this->nullableString($payload['status_pajak'] ?? null),
            'bpjs_kesehatan' => $this->nullableString($payload['bpjs_kesehatan'] ?? null),
            'bpjs_tk' => $this->nullableString($payload['bpjs_tk'] ?? null),
            'jam_kerja' => $this->nullableString($payload['jam_kerja'] ?? null),
            'skill' => $this->nullableString($payload['skill'] ?? null),
            'tinggi' => $this->nullableString($payload['tinggi'] ?? null),
            'berat' => $this->nullableString($payload['berat'] ?? null),
            'hobi' => $this->nullableString($payload['hobi'] ?? null),
            'no_jamsostek' => $this->nullableString($payload['no_jamsostek'] ?? null),
            'no_asuransi' => $this->nullableString($payload['no_asuransi'] ?? null),
            'no_kartu_asuransi' => $this->nullableString($payload['no_kartu_asuransi'] ?? null),
            'nama_bank' => $this->nullableString($payload['nama_bank'] ?? null),
            'no_rekening' => $this->nullableString($payload['no_rekening'] ?? null),
            'nama_instansi_pendidikan' => $this->nullableString($payload['nama_instansi_pendidikan'] ?? null),
            'pendidikan_terakhir' => $this->nullableString($payload['pendidikan_terakhir'] ?? null),
            'jurusan' => $this->nullableString($payload['jurusan'] ?? null),
            'tanggal_menikah' => !empty($payload['tanggal_menikah'] ?? null)
                ? Carbon::parse($payload['tanggal_menikah'])->format('Y-m-d')
                : null,
            'sisa_cuti' => isset($payload['sisa_cuti']) && $payload['sisa_cuti'] !== '' ? (float) $payload['sisa_cuti'] : null,
            'sisa_cuti_covid' => isset($payload['sisa_cuti_covid']) && $payload['sisa_cuti_covid'] !== '' ? (float) $payload['sisa_cuti_covid'] : null,
        ];

        foreach (['no_kk', 'npwp'] as $key) {
            $normalized[$key] = $normalized[$key] !== '' ? $normalized[$key] : null;
        }

        foreach ($this->candidateProfileIntegerColumns() as $key) {
            if (array_key_exists($key, $normalized) && !Schema::hasColumn('onboarding_candidates', $key)) {
                unset($normalized[$key]);
            }
        }

        $normalized['source_payload_hash'] = hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $normalized;
    }

    private function resolveCandidate(array $payload): OnboardingCandidate
    {
        $matches = OnboardingCandidate::query()
            ->where(function ($query) use ($payload) {
                if (!empty($payload['vhire_candidate_id'])) {
                    $query->where('vhire_candidate_id', $payload['vhire_candidate_id']);
                }

                $method = !empty($payload['vhire_candidate_id']) ? 'orWhere' : 'where';
                $query->{$method}('candidate_code', $payload['candidate_code'])
                    ->orWhere('no_ktp', $payload['no_ktp']);
            })
            ->lockForUpdate()
            ->get();

        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'candidate_code' => 'Data V-Hire cocok dengan lebih dari satu calon karyawan. Periksa duplikasi candidate_code/No KTP di HRIS.',
            ]);
        }

        return $matches->first() ?: new OnboardingCandidate();
    }

    private function createOrUpdatePkwtOneContract(OnboardingCandidate $candidate): EmployeeContract
    {
        $template = ContractTemplate::query()
            ->where('contract_type', ContractTemplate::TYPE_PKWT_1)
            ->where('is_active', true)
            ->orderBy('id')
            ->firstOrFail();

        $contract = EmployeeContract::query()
            ->where('onboarding_candidate_id', $candidate->id)
            ->where('contract_type', ContractTemplate::TYPE_PKWT_1)
            ->where('status', '!=', EmployeeContract::STATUS_CANCELLED)
            ->lockForUpdate()
            ->first();

        if (!$contract) {
            $contract = EmployeeContract::query()
                ->where(function ($query) use ($candidate) {
                    if ($candidate->vhire_candidate_id) {
                        $query->where('vhire_candidate_id', $candidate->vhire_candidate_id);
                    }

                    $method = $candidate->vhire_candidate_id ? 'orWhere' : 'where';
                    $query->{$method}('candidate_code', $candidate->candidate_code)
                        ->orWhere('no_ktp', $candidate->no_ktp);
                })
                ->where('contract_type', ContractTemplate::TYPE_PKWT_1)
                ->where('status', '!=', EmployeeContract::STATUS_CANCELLED)
                ->lockForUpdate()
                ->first();
        }

        $contract = $contract ?: new EmployeeContract();
        $isFinal = in_array($contract->signature_status, [
            EmployeeContract::SIGNATURE_STATUS_SIGNED,
            EmployeeContract::SIGNATURE_STATUS_REJECTED,
            EmployeeContract::SIGNATURE_STATUS_CANCELLED,
        ], true);

        if (!$isFinal) {
            $startDate = $candidate->tanggal_mulai_kerja ? Carbon::parse($candidate->tanggal_mulai_kerja) : null;
            $endDate = $candidate->tanggal_akhir_kontrak
                ? Carbon::parse($candidate->tanggal_akhir_kontrak)
                : $this->calculateEndDate($startDate, (int) $candidate->contract_duration_value, $candidate->contract_duration_unit);
            $durationLabel = $this->durationLabel((int) $candidate->contract_duration_value, $candidate->contract_duration_unit);

            $contract->fill([
                'onboarding_candidate_id' => $candidate->id,
                'vhire_candidate_id' => $candidate->vhire_candidate_id,
                'candidate_code' => $candidate->candidate_code,
                'no_ktp' => $candidate->no_ktp,
                'nik' => $candidate->employee_nik,
                'employee_nik' => $candidate->employee_nik,
                'candidate_name' => $candidate->nama,
                'contract_template_id' => $template->id,
                'contract_type' => ContractTemplate::TYPE_PKWT_1,
                'status' => EmployeeContract::STATUS_READY,
                'contract_number' => $contract->contract_number,
                'contract_code' => $candidate->kode_kontrak ?: ($contract->contract_code ?: $candidate->candidate_code),
                'pkwt_number' => $candidate->no_pkwt ?: ($contract->pkwt_number ?: $this->makePkwtNumber($candidate, $startDate)),
                'gender' => $candidate->jenis_kelamin,
                'marital_status' => $candidate->status_pernikahan,
                'address' => $candidate->alamat,
                'position' => $candidate->jabatan,
                'departemen' => $candidate->departemen,
                'lokasi' => $candidate->lokasi,
                'contract_duration' => $durationLabel,
                'contract_start_date' => optional($startDate)->format('Y-m-d'),
                'contract_end_date' => optional($endDate)->format('Y-m-d'),
                'duration_value' => $candidate->contract_duration_value,
                'duration_unit' => $candidate->contract_duration_unit,
                'salary' => $candidate->gaji !== null ? $candidate->gaji : ($contract->salary ?: 0),
                'meal_allowance' => $candidate->uang_makan !== null ? $candidate->uang_makan : ($contract->meal_allowance ?: 0),
                'signing_method' => $candidate->signing_method,
                'signature_status' => $contract->signature_status ?: EmployeeContract::SIGNATURE_STATUS_WAITING,
                'visible_in_vhire' => $candidate->signing_method === EmployeeContract::SIGNING_METHOD_ELECTRONIC && blank($candidate->employee_nik),
            ]);
        }

        $contract->save();
        $contract->rendered_html = $this->contractService->renderContractHtml($contract->fresh(['template', 'employee']));
        $contract->save();

        if ($candidate->onboarding_status !== OnboardingCandidate::STATUS_ACTIVATED) {
            $candidate->update(['onboarding_status' => OnboardingCandidate::STATUS_CONTRACT_GENERATED]);
        }

        return $contract;
    }

    private function resolveContractForSignature(EmployeeContract $contractFromRoute, array $payload): EmployeeContract
    {
        if (empty($payload['hris_contract_id']) && empty($payload['kode_kontrak']) && empty($payload['no_pkwt'])) {
            return EmployeeContract::query()->whereKey($contractFromRoute->id)->lockForUpdate()->firstOrFail();
        }

        $query = EmployeeContract::query();

        if (!empty($payload['hris_contract_id'])) {
            $query->orWhere('id', $payload['hris_contract_id']);
        }

        if (!empty($payload['kode_kontrak'])) {
            $query->orWhere('contract_code', $payload['kode_kontrak']);
        }

        if (!empty($payload['no_pkwt'])) {
            $query->orWhere('pkwt_number', $payload['no_pkwt']);
        }

        $matched = $query->lockForUpdate()->get();

        if ($matched->isEmpty()) {
            return EmployeeContract::query()->whereKey($contractFromRoute->id)->lockForUpdate()->firstOrFail();
        }

        if ($matched->pluck('id')->unique()->count() > 1) {
            throw ValidationException::withMessages([
                'hris_contract_id' => 'Callback V-Hire cocok dengan lebih dari satu kontrak HRIS.',
            ]);
        }

        return $matched->first();
    }

    private function storeVhireEmployeeSignatureIfPresent(
        EmployeeContract $contract,
        array $payload,
        Request $request
    ): ?EmployeeContractSignature {
        if (
            ($payload['signature_status'] ?? null) !== EmployeeContract::SIGNATURE_STATUS_SIGNED
            || empty($payload['employee_signature_base64'])
        ) {
            return $contract->signature;
        }

        $mime = $payload['employee_signature_mime'] ?? 'image/png';
        $decoded = $this->decodeSignatureImage((string) $payload['employee_signature_base64']);

        if ($decoded === null) {
            throw ValidationException::withMessages([
                'employee_signature_base64' => 'Data gambar tanda tangan dari V-Hire tidak valid.',
            ]);
        }

        if (!in_array($mime, ['image/png', 'image/jpeg'], true)) {
            throw ValidationException::withMessages([
                'employee_signature_mime' => 'Format gambar tanda tangan dari V-Hire tidak valid.',
            ]);
        }

        if (strlen($decoded) > 1024 * 1024) {
            throw ValidationException::withMessages([
                'employee_signature_base64' => 'Ukuran gambar tanda tangan dari V-Hire terlalu besar.',
            ]);
        }

        $hash = hash('sha256', $decoded);

        if (
            !empty($payload['employee_signature_hash'])
            && !hash_equals((string) $payload['employee_signature_hash'], $hash)
        ) {
            throw ValidationException::withMessages([
                'employee_signature_hash' => 'Hash tanda tangan dari V-Hire tidak cocok.',
            ]);
        }

        $existingSignature = $contract->signature()->first();

        if ($existingSignature && (string) $existingSignature->document_hash === $hash) {
            return $existingSignature;
        }

        $extension = $mime === 'image/jpeg' ? 'jpg' : 'png';
        $directoryKey = preg_replace('/[^A-Za-z0-9\-]+/', '-', (string) (
            $contract->nik ?: $contract->employee_nik ?: $contract->candidate_code ?: $contract->no_ktp ?: $contract->id
        ));
        $path = sprintf(
            'employee-contract-signatures/vhire/%s/%s/%s.%s',
            $directoryKey ?: 'candidate',
            $contract->id,
            Str::uuid(),
            $extension
        );

        Storage::put($path, $decoded);

        $signedAt = !empty($payload['signed_at']) ? Carbon::parse($payload['signed_at']) : ($contract->signed_at ?: now());

        return EmployeeContractSignature::updateOrCreate(
            ['employee_contract_id' => $contract->id],
            [
                'nik' => (string) ($contract->nik ?: $contract->employee_nik ?: $contract->candidate_code ?: $contract->no_ktp),
                'signed_by_user_id' => Str::limit('vhire:' . ($contract->vhire_candidate_id ?: $contract->candidate_code ?: $contract->id), 64, ''),
                'signature_path' => $path,
                'signed_at' => $signedAt,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'document_hash' => $hash,
                'consent_text' => 'Tanda tangan elektronik kandidat dilakukan melalui V-Hire untuk kontrak PKWT 1 ini.',
            ]
        );
    }

    private function decodeSignatureImage(string $signatureData): ?string
    {
        if (preg_match('/^data:image\/(?:png|jpe?g);base64,/i', $signatureData, $matches)) {
            $signatureData = substr($signatureData, strlen($matches[0]));
        }

        $signatureData = preg_replace('/\s+/', '', $signatureData);

        if (!is_string($signatureData) || $signatureData === '') {
            return null;
        }

        $decoded = base64_decode($signatureData, true);

        return $decoded === false || strlen($decoded) < 100 ? null : $decoded;
    }

    private function syncCandidateProfileFromIntegrationPayload(EmployeeContract $contract, array $payload): void
    {
        if (!$contract->onboarding_candidate_id) {
            return;
        }

        $candidate = OnboardingCandidate::query()
            ->whereKey($contract->onboarding_candidate_id)
            ->lockForUpdate()
            ->first();

        if (!$candidate) {
            return;
        }

        $updates = [];

        foreach ($this->candidateProfileTextColumns() as $column) {
            if (
                array_key_exists($column, $payload)
                && Schema::hasColumn('onboarding_candidates', $column)
                && $this->nullableString($payload[$column]) !== null
            ) {
                $updates[$column] = $this->nullableString($payload[$column]);
            }
        }

        foreach ($this->candidateProfileIntegerColumns() as $column) {
            if (
                array_key_exists($column, $payload)
                && Schema::hasColumn('onboarding_candidates', $column)
                && $this->nullableInteger($payload[$column]) !== null
            ) {
                $updates[$column] = $this->nullableInteger($payload[$column]);
            }
        }

        if (!$updates) {
            return;
        }

        $updates['last_synced_at'] = now();
        $candidate->fill($updates);
        $candidate->save();
    }

    private function candidateProfileTextColumns(): array
    {
        return ['departemen', 'divisi'];
    }

    private function candidateProfileIntegerColumns(): array
    {
        return [
            'departemen_id',
            'divisi_id',
            'provinsi_id',
            'kabupaten_id',
            'kecamatan_id',
            'kelurahan_id',
        ];
    }

    private function assertContractCandidateMatches(EmployeeContract $contract, array $payload): void
    {
        $payloadVhireCandidateId = trim((string) ($payload['vhire_candidate_id'] ?? ''));
        $contractVhireCandidateId = trim((string) $contract->vhire_candidate_id);

        if (
            ($payloadVhireCandidateId !== '' && $contractVhireCandidateId !== '' && $contractVhireCandidateId !== $payloadVhireCandidateId)
            || (string) $contract->candidate_code !== (string) $payload['candidate_code']
            || (string) $contract->no_ktp !== (string) $payload['no_ktp']
        ) {
            throw ValidationException::withMessages([
                'vhire_candidate_id' => 'Data kandidat pada callback tidak cocok dengan kontrak HRIS.',
            ]);
        }
    }

    private function calculateEndDate(?Carbon $startDate, int $value, string $unit): ?Carbon
    {
        if (!$startDate) {
            return null;
        }

        if ($unit === 'day') {
            return $startDate->copy()->addDays($value);
        }

        if ($unit === 'year') {
            return $startDate->copy()->addYearsNoOverflow($value);
        }

        return $startDate->copy()->addMonthsNoOverflow($value);
    }

    private function normalizeDurationUnit(string $unit): string
    {
        $unit = Str::lower(trim($unit));

        if (in_array($unit, ['day', 'days', 'hari'], true)) {
            return 'day';
        }

        if (in_array($unit, ['year', 'years', 'tahun'], true)) {
            return 'year';
        }

        return 'month';
    }

    private function durationLabel(int $value, string $unit): string
    {
        $labels = [
            'day' => 'hari',
            'month' => 'bulan',
            'year' => 'tahun',
        ];

        return trim($value . ' ' . ($labels[$unit] ?? $unit));
    }

    private function makePkwtNumber(OnboardingCandidate $candidate, ?Carbon $startDate): string
    {
        $date = $startDate ?: now();
        $month = function_exists('bulan_romawi') ? bulan_romawi((int) $date->format('n')) : $date->format('m');

        return sprintf(
            'PKWT1/%s/HRD/%s/%s',
            preg_replace('/[^A-Za-z0-9\-]+/', '-', $candidate->candidate_code),
            $month,
            $date->format('Y')
        );
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInteger($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value) && (int) $value > 0) {
            return (int) $value;
        }

        return null;
    }

    private function recordInboundSyncLog(
        string $operation,
        Request $request,
        array $payload,
        ?string $relatedType,
        ?int $relatedId
    ): void {
        VhireSyncLog::create([
            'direction' => VhireSyncLog::DIRECTION_INBOUND,
            'operation' => $operation,
            'method' => $request->method(),
            'endpoint' => '/' . ltrim($request->path(), '/'),
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'status' => VhireSyncLog::STATUS_SUCCESS,
            'attempt_count' => 1,
            'http_status' => 200,
            'idempotency_key' => $request->header('Idempotency-Key'),
            'request_payload_summary' => $this->sanitizer->summary($payload),
            'last_attempt_at' => now(),
        ]);
    }

    private function recordAudit(?EmployeeContract $contract, string $event, Request $request, array $metadata = []): void
    {
        ElectronicContractAuditLog::create([
            'employee_contract_id' => optional($contract)->id,
            'nik' => optional($contract)->nik ?: optional($contract)->employee_nik,
            'event' => $event,
            'actor_user_id' => optional($request->user())->id,
            'actor_name' => optional($request->user())->name ?: 'V-Hire API',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $metadata,
        ]);
    }

    private function recordAuditFromContext(?EmployeeContract $contract, string $event, array $context = []): void
    {
        ElectronicContractAuditLog::create([
            'employee_contract_id' => optional($contract)->id,
            'nik' => optional($contract)->nik ?: optional($contract)->employee_nik,
            'event' => $event,
            'actor_user_id' => $context['actor_user_id'] ?? null,
            'actor_name' => $context['actor_name'] ?? 'HRIS System',
            'ip_address' => null,
            'user_agent' => null,
            'metadata' => $context['metadata'] ?? [],
        ]);
    }
}
