<?php

namespace App\Jobs;

use App\Models\EmployeeContract;
use App\Models\OnboardingCandidate;
use App\Models\VhireSyncLog;
use App\Services\Vhire\VhireApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class SyncVhireOutbound implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 120;

    private int $syncLogId;

    public function __construct(int $syncLogId)
    {
        $this->syncLogId = $syncLogId;
    }

    public function handle(VhireApiClient $client): void
    {
        $log = VhireSyncLog::find($this->syncLogId);

        if (!$log || $log->status !== VhireSyncLog::STATUS_QUEUED) {
            return;
        }

        $log->update([
            'attempt_count' => (int) $log->attempt_count + 1,
            'last_attempt_at' => now(),
        ]);

        try {
            if ($log->operation === VhireSyncLog::OPERATION_CONTRACT_SYNC) {
                $this->syncContract($client, $log);
                return;
            }

            if ($log->operation === VhireSyncLog::OPERATION_ACTIVATION_SYNC) {
                $this->syncActivation($client, $log);
                return;
            }

            $log->update([
                'status' => VhireSyncLog::STATUS_SKIPPED,
                'error_message' => 'Operation outbound V-Hire tidak dikenal.',
            ]);
        } catch (Throwable $exception) {
            $log->update([
                'status' => VhireSyncLog::STATUS_FAILED,
                'error_message' => Str::limit($exception->getMessage(), 1000),
                'next_retry_at' => now()->addMinutes(5),
            ]);
        }
    }

    private function syncContract(VhireApiClient $client, VhireSyncLog $log): void
    {
        $contract = EmployeeContract::find($log->related_id);

        if (!$contract) {
            $log->update([
                'status' => VhireSyncLog::STATUS_SKIPPED,
                'error_message' => 'Kontrak HRIS tidak ditemukan.',
            ]);
            return;
        }

        $result = $client->sendContract($contract, (string) $log->idempotency_key);

        $log->update([
            'status' => VhireSyncLog::STATUS_SUCCESS,
            'http_status' => $result['http_status'] ?? null,
            'response_summary' => $result['body'] ?? null,
            'error_message' => null,
            'next_retry_at' => null,
        ]);

        $contract->update([
            'vhire_contract_synced_at' => now(),
        ]);
    }

    private function syncActivation(VhireApiClient $client, VhireSyncLog $log): void
    {
        $candidate = OnboardingCandidate::find($log->related_id);

        if (!$candidate) {
            $log->update([
                'status' => VhireSyncLog::STATUS_SKIPPED,
                'error_message' => 'Data onboarding candidate tidak ditemukan.',
            ]);
            return;
        }

        $result = $client->sendActivation($candidate, (string) $log->idempotency_key);

        $log->update([
            'status' => VhireSyncLog::STATUS_SUCCESS,
            'http_status' => $result['http_status'] ?? null,
            'response_summary' => $result['body'] ?? null,
            'error_message' => null,
            'next_retry_at' => null,
        ]);

        EmployeeContract::query()
            ->where('onboarding_candidate_id', $candidate->id)
            ->update(['vhire_activation_synced_at' => now()]);
    }
}
