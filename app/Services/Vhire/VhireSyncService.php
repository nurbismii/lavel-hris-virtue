<?php

namespace App\Services\Vhire;

use App\Jobs\SyncVhireOutbound;
use App\Models\EmployeeContract;
use App\Models\OnboardingCandidate;
use App\Models\User;
use App\Models\VhireSyncLog;
use Illuminate\Support\Str;

class VhireSyncService
{
    private VhireApiClient $client;
    private VhirePayloadSanitizer $sanitizer;

    public function __construct(VhireApiClient $client, VhirePayloadSanitizer $sanitizer)
    {
        $this->client = $client;
        $this->sanitizer = $sanitizer;
    }

    public function queueContractSync(EmployeeContract $contract, ?User $actor = null): VhireSyncLog
    {
        $idempotencyKey = 'contract:' . $contract->id . ':' . sha1((string) $contract->updated_at);
        $payload = $this->client->contractPayload($contract);

        $log = VhireSyncLog::create([
            'direction' => VhireSyncLog::DIRECTION_OUTBOUND,
            'operation' => VhireSyncLog::OPERATION_CONTRACT_SYNC,
            'method' => 'POST',
            'endpoint' => $this->safeEndpoint('/api/vhire/contracts'),
            'related_type' => EmployeeContract::class,
            'related_id' => $contract->id,
            'status' => VhireSyncLog::STATUS_QUEUED,
            'idempotency_key' => $idempotencyKey,
            'request_payload_summary' => $this->sanitizer->summary($payload),
            'created_by' => optional($actor)->id,
        ]);

        SyncVhireOutbound::dispatch($log->id)->onQueue((string) config('services.vhire.queue', 'default'));

        return $log;
    }

    public function queueActivationSync(OnboardingCandidate $candidate, ?User $actor = null): VhireSyncLog
    {
        $idempotencyKey = 'activation:' . $candidate->id . ':' . sha1((string) $candidate->updated_at);
        $endpoint = $candidate->vhire_candidate_id
            ? '/api/vhire/candidates/' . rawurlencode($candidate->vhire_candidate_id) . '/activated'
            : '/api/vhire/candidates/activated';

        $payload = [
            'vhire_candidate_id' => $candidate->vhire_candidate_id,
            'candidate_code' => $candidate->candidate_code,
            'no_ktp' => $candidate->no_ktp,
            'employee_nik' => $candidate->employee_nik,
            'activated_as_employee_at' => optional($candidate->activated_as_employee_at)->format('Y-m-d H:i:s'),
        ];

        $log = VhireSyncLog::create([
            'direction' => VhireSyncLog::DIRECTION_OUTBOUND,
            'operation' => VhireSyncLog::OPERATION_ACTIVATION_SYNC,
            'method' => 'POST',
            'endpoint' => $this->safeEndpoint($endpoint),
            'related_type' => OnboardingCandidate::class,
            'related_id' => $candidate->id,
            'status' => VhireSyncLog::STATUS_QUEUED,
            'idempotency_key' => $idempotencyKey,
            'request_payload_summary' => $this->sanitizer->summary($payload),
            'created_by' => optional($actor)->id,
        ]);

        SyncVhireOutbound::dispatch($log->id)->onQueue((string) config('services.vhire.queue', 'default'));

        return $log;
    }

    public function retry(VhireSyncLog $log, ?User $actor = null): VhireSyncLog
    {
        if ($log->status !== VhireSyncLog::STATUS_FAILED) {
            return $log;
        }

        $log->update([
            'status' => VhireSyncLog::STATUS_QUEUED,
            'error_message' => null,
            'next_retry_at' => null,
            'created_by' => optional($actor)->id ?: $log->created_by,
        ]);

        SyncVhireOutbound::dispatch($log->id)->onQueue((string) config('services.vhire.queue', 'default'));

        return $log->fresh();
    }

    private function safeEndpoint(string $path): string
    {
        try {
            return $this->client->endpoint($path);
        } catch (\Throwable $exception) {
            return Str::limit($path, 500, '');
        }
    }
}
