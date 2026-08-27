<?php

namespace App\Jobs;

use App\Models\ImportHistory;
use App\Services\Audit\AuditTrailService;
use App\Services\Roster\RosterScheduleImportCommitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ProcessRosterScheduleImport implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 900;
    public int $uniqueFor = 3600;

    public function __construct(public readonly int $historyId)
    {
    }

    public function uniqueId(): string
    {
        return 'roster-import-' . $this->historyId;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter(960)];
    }

    public function handle(RosterScheduleImportCommitService $commit, AuditTrailService $audit): void
    {
        $claimed = ImportHistory::query()
            ->whereKey($this->historyId)
            ->where('import_type', ImportHistory::TYPE_ROSTER_SCHEDULE)
            ->where('status', ImportHistory::STATUS_QUEUED)
            ->where('expires_at', '>', now())
            ->update(['status' => ImportHistory::STATUS_PROCESSING, 'started_at' => now()]);

        if ($claimed !== 1) {
            return;
        }

        $history = ImportHistory::query()->find($this->historyId);
        if (!$history) {
            return;
        }

        try {
            $result = $commit->commit($history);
        } catch (Throwable $exception) {
            if ($this->attempts() < $this->tries) {
                ImportHistory::query()
                    ->whereKey($this->historyId)
                    ->where('status', ImportHistory::STATUS_PROCESSING)
                    ->update(['status' => ImportHistory::STATUS_QUEUED]);
            }

            throw $exception;
        }
        $audit->record([
            'event' => 'roster_schedule_import.completed',
            'module' => 'roster_schedule_import',
            'reference_table' => 'import_histories',
            'reference_id' => (string) $history->id,
            'actor_id' => $history->confirmed_by,
            'metadata' => ['import_id' => $history->import_id, 'status' => ImportHistory::STATUS_COMPLETED, 'summary' => $this->safeResult($result)],
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $history = ImportHistory::query()->find($this->historyId);
        if (!$history || $history->status === ImportHistory::STATUS_COMPLETED) {
            return;
        }

        $history->update([
            'status' => ImportHistory::STATUS_FAILED,
            'error_message' => 'Import roster gagal diproses. Silakan unggah ulang workbook.',
            'finished_at' => now(),
        ]);
        app(AuditTrailService::class)->record([
            'event' => 'roster_schedule_import.failed',
            'module' => 'roster_schedule_import',
            'reference_table' => 'import_histories',
            'reference_id' => (string) $history->id,
            'actor_id' => $history->confirmed_by,
            'metadata' => ['import_id' => $history->import_id, 'status' => ImportHistory::STATUS_FAILED],
        ]);
    }

    private function safeResult(array $result): array
    {
        return collect($result)->only([
            'employees', 'history_created', 'history_updated', 'unchanged', 'future_generated', 'need_review',
        ])->map(fn ($value) => max(0, (int) $value))->all();
    }
}
