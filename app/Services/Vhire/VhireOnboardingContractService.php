<?php

namespace App\Services\Vhire;

use App\Models\ContractTemplate;
use App\Models\ElectronicContractAuditLog;
use App\Models\EmployeeContract;
use App\Models\EmployeeContractSignature;
use App\Models\OnboardingCandidate;
use App\Models\VhireSyncLog;
use App\Services\ElectronicContracts\ElectronicContractService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

            $this->recordAudit($updatedContract, 'vhire_candidate_activated_as_employee', $request, [
                'vhire_candidate_id' => $candidate->vhire_candidate_id,
                'candidate_code' => $candidate->candidate_code,
                'employee_nik' => $employeeNik,
            ]);

            return $candidate->fresh();
        });

        $this->syncService->queueActivationSync($candidate, $request->user());

        return $candidate;
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
            'jabatan' => $this->nullableString($payload['jabatan'] ?? null),
            'tanggal_mulai_kerja' => !empty($payload['tanggal_mulai_kerja'])
                ? Carbon::parse($payload['tanggal_mulai_kerja'])->format('Y-m-d')
                : null,
            'tanggal_akhir_kontrak' => !empty($payload['tanggal_akhir_kontrak'])
                ? Carbon::parse($payload['tanggal_akhir_kontrak'])->format('Y-m-d')
                : null,
            'departemen' => $this->nullableString($payload['departemen'] ?? null),
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
        ];

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
