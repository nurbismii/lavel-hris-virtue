<?php

namespace App\Console\Commands;

use App\Models\VhireSyncLog;
use App\Services\Vhire\VhireSyncService;
use Illuminate\Console\Command;

class RetryFailedVhireSyncs extends Command
{
    protected $signature = 'vhire:retry-failed-syncs {--limit=50 : Maksimal log gagal yang dimasukkan ulang ke queue}';

    protected $description = 'Retry outbound V-Hire sync logs that are due for retry.';

    public function handle(VhireSyncService $syncService): int
    {
        $limit = max(1, min((int) $this->option('limit'), 200));
        $maxAttempts = max(1, (int) config('services.vhire.max_retry_attempts', 3));

        $logs = VhireSyncLog::query()
            ->failed()
            ->where('direction', VhireSyncLog::DIRECTION_OUTBOUND)
            ->where('attempt_count', '<', $maxAttempts)
            ->where(function ($query) {
                $query->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            })
            ->orderByRaw('next_retry_at IS NULL')
            ->orderBy('next_retry_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($logs as $log) {
            $syncService->retry($log);
        }

        $this->info('V-Hire sync retry queued: ' . $logs->count());

        return 0;
    }
}
