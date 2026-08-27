<?php

namespace App\Services\Roster;

use App\Models\ImportHistory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Backward-compatible CLI adapter. HTTP imports must use preview + confirmation.
 */
class RosterScheduleWorkbookImportService
{
    public function __construct(
        private readonly RosterScheduleWorkbookReader $reader,
        private readonly RosterScheduleImportValidationService $validator,
        private readonly RosterScheduleImportCommitService $commit
    ) {
    }

    public function import(string $path, bool $dryRun = false, ?string $actorId = null): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Workbook roster tidak tersedia untuk diproses.');
        }

        $validation = $this->validator->validate($this->reader->read($path));
        $summary = $this->safeSummary($validation['summary']);
        if (!$validation['is_valid']) {
            throw new RuntimeException('Workbook roster tidak valid untuk diproses.');
        }

        if ($dryRun) {
            return $summary;
        }

        $importId = (string) Str::uuid();
        $relativePath = 'roster-imports/' . $importId . '/source.xlsx';
        Storage::disk('local')->put('private/' . $relativePath, file_get_contents($path));
        $absolutePath = Storage::disk('local')->path('private/' . $relativePath);
        $history = ImportHistory::query()->create([
            'import_id' => $importId,
            'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_PROCESSING,
            'created_by' => $actorId,
            'confirmed_by' => $actorId,
            'confirmed_at' => now(),
            'file_path' => $relativePath,
            'file_checksum' => hash_file('sha256', $absolutePath),
            'expires_at' => now()->addHours((int) config('roster.import.retention_hours', 12)),
        ]);

        return $this->safeSummary($this->commit->commit($history));
    }

    private function safeSummary(array $summary): array
    {
        return collect($summary)->only([
            'total_rows', 'blocker_count', 'warning_count', 'employees', 'history_created', 'history_updated',
            'unchanged', 'future_generated', 'need_review',
        ])->map(fn ($value) => max(0, (int) $value))->all();
    }
}
