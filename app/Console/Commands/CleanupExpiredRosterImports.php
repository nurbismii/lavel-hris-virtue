<?php

namespace App\Console\Commands;

use App\Models\ImportHistory;
use App\Services\Audit\AuditTrailService;
use App\Services\Storage\SensitiveFileStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CleanupExpiredRosterImports extends Command
{
    private const ALLOWED_PREFIX = 'roster-imports/';
    private const CHUNK_SIZE = 100;

    protected $signature = 'roster:cleanup-expired-imports {--limit=500 : Maksimal riwayat import yang dibersihkan}';

    protected $description = 'Membersihkan file private import roster yang sudah kedaluwarsa.';

    public function handle(SensitiveFileStorageService $storage, AuditTrailService $audit): int
    {
        $remaining = max(1, min(2000, (int) $this->option('limit')));
        $lastId = 0;
        $cleaned = 0;
        $errors = 0;

        while ($remaining > 0) {
            $histories = ImportHistory::query()
                ->where('import_type', ImportHistory::TYPE_ROSTER_SCHEDULE)
                ->where('expires_at', '<=', now())
                ->where(function ($query): void {
                    $query->whereNotNull('file_path')
                        ->orWhereNotNull('failure_file_path');
                })
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(min(self::CHUNK_SIZE, $remaining))
                ->get();

            if ($histories->isEmpty()) {
                break;
            }

            foreach ($histories as $history) {
                $lastId = $history->id;
                $result = $this->cleanupHistory($history, $storage, $audit);
                $cleaned += $result['cleaned'] ? 1 : 0;
                $errors += $result['errors'];
            }

            $remaining -= $histories->count();
        }

        $this->info($cleaned . ' riwayat import roster dibersihkan.');

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function cleanupHistory(
        ImportHistory $history,
        SensitiveFileStorageService $storage,
        AuditTrailService $audit
    ): array {
        $changes = [];
        $deletedFileCount = 0;
        $errors = 0;
        $previousStatus = $history->status;

        foreach (['file_path', 'failure_file_path'] as $column) {
            if (!$history->{$column}) {
                continue;
            }

            try {
                $result = $this->cleanupPath((string) $history->{$column}, $storage);
                if ($result['cleared']) {
                    $changes[$column] = null;
                    $deletedFileCount += $result['deleted'] ? 1 : 0;
                }
            } catch (\Throwable $exception) {
                $errors++;
                $this->logFailure($history, $exception);
            }
        }

        $sourcePath = array_key_exists('file_path', $changes) ? null : $history->file_path;
        $failurePath = array_key_exists('failure_file_path', $changes) ? null : $history->failure_file_path;
        if (!$sourcePath && !$failurePath && in_array($history->status, [
            ImportHistory::STATUS_AWAITING_CONFIRMATION,
            ImportHistory::STATUS_VALIDATION_FAILED,
        ], true)) {
            $changes['status'] = ImportHistory::STATUS_EXPIRED;
        }

        if ($changes === []) {
            return ['cleaned' => false, 'errors' => $errors];
        }

        try {
            $history->update($changes);
        } catch (\Throwable $exception) {
            $errors++;
            $this->logHistoryUpdateFailure($history, $exception);

            return ['cleaned' => false, 'errors' => $errors];
        }

        try {
            $audit->record([
                'event' => 'roster_schedule_import.cleaned',
                'module' => 'roster_schedule_import',
                'reference_table' => 'import_histories',
                'reference_id' => (string) $history->id,
                'actor_id' => 'system',
                'actor_name' => 'system',
                'actor_role' => 'system',
                'metadata' => [
                    'import_id' => $history->import_id,
                    'previous_status' => $previousStatus,
                    'final_status' => $history->fresh()->status,
                    'deleted_file_count' => $deletedFileCount,
                ],
            ]);
        } catch (\Throwable $exception) {
            $errors++;
            $this->logAuditFailure($history, $exception);
        }

        return ['cleaned' => true, 'errors' => $errors];
    }

    private function cleanupPath(string $path, SensitiveFileStorageService $storage): array
    {
        if (!$this->isAllowedPath($path)) {
            throw new InvalidArgumentException('Invalid roster import cleanup path.');
        }

        $exists = $storage->resolvePrivatePath($path, [self::ALLOWED_PREFIX]) !== null;
        if ($exists) {
            $storage->deletePrivate($path, [self::ALLOWED_PREFIX]);
        }

        if ($storage->resolvePrivatePath($path, [self::ALLOWED_PREFIX]) !== null) {
            throw new \RuntimeException('Roster import file could not be removed.');
        }

        return ['cleared' => true, 'deleted' => $exists];
    }

    private function isAllowedPath(string $path): bool
    {
        return Str::startsWith($path, self::ALLOWED_PREFIX)
            && !Str::contains($path, '..');
    }

    private function logFailure(ImportHistory $history, \Throwable $exception): void
    {
        Log::warning('Roster import cleanup failed.', [
            'code' => 'roster_import_cleanup_failed',
            'import_id' => (string) $history->import_id,
            'exception_class' => get_class($exception),
        ]);
    }

    private function logAuditFailure(ImportHistory $history, \Throwable $exception): void
    {
        Log::warning('Roster import cleanup audit failed.', [
            'code' => 'roster_import_cleanup_audit_failed',
            'import_id' => (string) $history->import_id,
            'exception_class' => get_class($exception),
        ]);
    }

    private function logHistoryUpdateFailure(ImportHistory $history, \Throwable $exception): void
    {
        Log::warning('Roster import cleanup history update failed.', [
            'code' => 'roster_import_cleanup_history_update_failed',
            'import_id' => (string) $history->import_id,
            'exception_class' => get_class($exception),
        ]);
    }
}
