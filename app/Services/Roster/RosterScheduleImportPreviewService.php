<?php

namespace App\Services\Roster;

use App\Exports\RosterScheduleImportFailuresExport;
use App\Models\ImportHistory;
use App\Models\User;
use App\Services\ImportHistory\ImportHistoryService;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

final class RosterScheduleImportPreviewService
{
    public function __construct(
        private RosterScheduleWorkbookReader $reader,
        private RosterScheduleImportValidationService $validator,
        private ImportHistoryService $historyService
    ) {
    }

    public function preview(ImportHistory $history, User $actor): array
    {
        if (!$this->canPreview($history, $actor)) {
            throw new RuntimeException('Anda tidak memiliki akses untuk memproses import ini.');
        }

        $path = (string) $history->file_path;
        $prefix = 'roster-imports/' . $history->import_id . '/';
        if (!str_starts_with($path, $prefix) || str_contains($path, '..') || !Storage::disk('local')->exists('private/' . $path)) {
            throw new RuntimeException('Path import tidak valid.');
        }

        $result = $this->validator->validate($this->reader->read(Storage::disk('local')->path('private/' . $path)));
        if ($result['is_valid']) {
            $this->historyService->markAwaitingConfirmation($history->id, $result['summary']);

            return $result;
        }

        $failurePath = 'roster-imports/' . $history->import_id . '/failures.xlsx';
        $failures = array_values(array_filter($result['rows'], fn (array $row): bool => !empty($row['errors'])));
        if (!Excel::store(new RosterScheduleImportFailuresExport($failures), 'private/' . $failurePath, 'local')) {
            throw new RuntimeException('Workbook kegagalan tidak dapat disimpan.');
        }

        $this->historyService->markValidationFailed($history->id, $result['summary'], $result['rows'], $failurePath);

        return $result;
    }

    private function canPreview(ImportHistory $history, User $actor): bool
    {
        return $history->import_type === ImportHistory::TYPE_ROSTER_SCHEDULE
            && $history->status === ImportHistory::STATUS_QUEUED
            && (string) $history->created_by === (string) $actor->id
            && $actor->canAccessAllEmployees()
            && $actor->hasMenuAccess('roster_schedule');
    }
}
