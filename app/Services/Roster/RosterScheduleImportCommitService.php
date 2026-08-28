<?php

namespace App\Services\Roster;

use App\Models\Employee;
use App\Models\ImportHistory;
use App\Models\RosterSchedule;
use App\Models\RosterScheduleHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class RosterScheduleImportCommitService
{
    private const QUERY_CHUNK_SIZE = 200;

    public function __construct(
        private readonly RosterScheduleWorkbookReader $reader,
        private readonly RosterScheduleImportValidationService $validator,
        private readonly RosterHistoryRemarkParser $remarkParser,
        private readonly RosterScheduleService $rosterService
    ) {
    }

    public function commit(ImportHistory $history): array
    {
        $path = $this->preflightPath($history, [ImportHistory::STATUS_PROCESSING]);
        $data = $this->reader->read($path);
        $validation = $this->validator->validate($data);
        if (!$validation['is_valid']) {
            throw new RuntimeException('Workbook roster tidak lagi valid untuk diproses.');
        }

        $entries = $this->entries($data);
        if ($entries === []) {
            throw new RuntimeException('Workbook roster tidak lagi valid untuk diproses.');
        }

        return DB::transaction(function () use ($history, $path, $data, $entries): array {
            $lockedHistory = ImportHistory::query()->lockForUpdate()->find($history->id);
            if (!$lockedHistory || $lockedHistory->import_type !== ImportHistory::TYPE_ROSTER_SCHEDULE
                || $lockedHistory->status !== ImportHistory::STATUS_PROCESSING
                || !$lockedHistory->expires_at?->isFuture()
                || !hash_equals((string) $lockedHistory->file_checksum, hash_file('sha256', $path))) {
                throw new RuntimeException('Import roster tidak valid untuk diproses.');
            }

            $niks = array_values(array_unique(array_column($entries, 'nik')));
            $employees = Employee::query()->whereIn('nik', $niks)->lockForUpdate()->get()->keyBy('nik');
            if ($employees->count() !== count($niks)) {
                throw new RuntimeException('Identitas karyawan berubah sebelum import diproses.');
            }
            foreach ($entries as $entry) {
                $employee = $employees->get($entry['nik']);
                if (!$employee || !hash_equals((string) $employee->no_ktp, $entry['ktp'])) {
                    throw new RuntimeException('Identitas karyawan berubah sebelum import diproses.');
                }
            }

            $existingSchedules = $this->existingSchedules($entries);
            foreach ($entries as $entry) {
                $schedule = $existingSchedules->get($entry['pair']);
                if ($schedule && $schedule->source === RosterSchedule::SOURCE_MANUAL) {
                    throw new RuntimeException('Jadwal manual tidak dapat ditimpa.');
                }
            }

            $existingHistory = $this->existingHistories($existingSchedules);
            $scheduleRows = [];
            foreach ($entries as $entry) {
                $employee = $employees->get($entry['nik']);
                $remark = $this->remarkParser->parse($entry['raw_remark'], $entry['period_number']);
                $historyKey = $this->historyKey($entry);
                $confirmed = $existingHistory->get($historyKey);
                $schedule = $existingSchedules->get($entry['pair']);
                $realization = $confirmed && $confirmed->review_status === RosterScheduleHistory::REVIEW_CONFIRMED && $schedule
                    ? $schedule->realization_type
                    : $this->realization($remark['classification']);
                $scheduleRows[] = [
                    'employee_nik' => $entry['nik'],
                    'off_start' => $entry['off_start'],
                    'period_year' => $entry['year'],
                    'period_number' => $entry['period_number'],
                    'work_start' => $entry['off']->copy()->subDays($this->workDays())->toDateString(),
                    'work_end' => $entry['off']->copy()->subDay()->toDateString(),
                    'off_end' => $entry['off']->copy()->addDays($this->offDays() - 1)->toDateString(),
                    'earned_off_days' => max(0, (int) config('roster.earned_off_days', 5)),
                    'realization_type' => $realization,
                    'source' => RosterSchedule::SOURCE_IMPORT,
                    'notes' => $entry['raw_remark'],
                    'is_active' => $employee->status_resign === 'AKTIF',
                    'created_by' => $lockedHistory->confirmed_by,
                    'updated_by' => $lockedHistory->confirmed_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($scheduleRows, self::QUERY_CHUNK_SIZE) as $chunk) {
                RosterSchedule::query()->upsert($chunk, ['employee_nik', 'off_start'], [
                    'period_year', 'period_number', 'work_start', 'work_end', 'off_end', 'earned_off_days',
                    'realization_type', 'source', 'notes', 'is_active', 'updated_by', 'updated_at',
                ]);
            }
            $schedules = $this->existingSchedules($entries);

            $historyRows = [];
            $result = $this->emptyResult();
            foreach ($entries as $entry) {
                $schedule = $schedules->get($entry['pair']);
                $historyKey = $this->historyKey($entry);
                $previous = $existingHistory->get($historyKey);
                if ($previous && $previous->review_status === RosterScheduleHistory::REVIEW_CONFIRMED) {
                    $result['history_updated']++;
                } else {
                    $remark = $this->remarkParser->parse($entry['raw_remark'], $entry['period_number']);
                    $historyRows[] = [
                        'roster_schedule_id' => $schedule->id,
                        'employee_nik' => $entry['nik'],
                        'period_year' => $entry['year'],
                        'period_number' => $entry['period_number'],
                        'scheduled_off_start' => $entry['off_start'],
                        'scheduled_off_end' => $entry['off']->copy()->addDays($this->offDays() - 1)->toDateString(),
                        'classification' => $remark['classification'],
                        'review_status' => $remark['review_status'],
                        'remark_segment' => $remark['remark_segment'],
                        'raw_remark' => $entry['raw_remark'],
                        'source_file' => 'source.xlsx',
                        'source_sheet' => $data->sheetName,
                        'source_row' => $entry['row_number'],
                        'source_column' => $entry['source_column'],
                        'source_remark_column' => $entry['remark_column'],
                        'imported_at' => now(),
                        'imported_by' => $lockedHistory->confirmed_by,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $result[$previous ? 'history_updated' : 'history_created']++;
                    if ($remark['review_status'] === RosterScheduleHistory::REVIEW_PENDING) {
                        $result['need_review']++;
                    }
                }
                if ($entry['off']->betweenIncluded(Carbon::today(), Carbon::today()->addDays(13))) {
                    $result['late_candidate_schedule_ids'][] = $schedule->id;
                }
            }
            foreach (array_chunk($historyRows, self::QUERY_CHUNK_SIZE) as $chunk) {
                RosterScheduleHistory::query()->upsert($chunk, [
                    'employee_nik', 'period_year', 'period_number', 'scheduled_off_start', 'source_file',
                ], [
                    'roster_schedule_id', 'scheduled_off_end', 'classification', 'review_status', 'remark_segment',
                    'raw_remark', 'source_sheet', 'source_row', 'source_column', 'source_remark_column', 'imported_at',
                    'imported_by', 'updated_at',
                ]);
            }

            foreach ($employees as $employee) {
                if ($employee->status_resign === 'AKTIF') {
                    $result['future_generated'] += $this->rosterService->generateUntil(
                        $employee,
                        now()->addYears(max(1, (int) config('roster.generate_years_ahead', 2)))->endOfYear(),
                        $lockedHistory->confirmed_by,
                        false
                    )->count();
                }
            }
            $this->rosterService->synchronizeSequences($niks);
            $result['employees'] = count($niks);
            $result['unchanged'] = max(0, count($entries) - $result['history_created']);
            $lockedHistory->update([
                'status' => ImportHistory::STATUS_COMPLETED,
                'summary' => $this->publicSummary($result),
                'finished_at' => now(),
            ]);

            return $result;
        });
    }

    public function preflight(ImportHistory $history): void
    {
        $path = $this->preflightPath($history, [ImportHistory::STATUS_AWAITING_CONFIRMATION]);
        $validation = $this->validator->validate($this->reader->read($path));
        if (!$validation['is_valid']) {
            throw new RuntimeException('Workbook roster tidak lagi valid untuk diproses.');
        }
    }

    private function entries($data): array
    {
        $entries = [];
        foreach ($data->rows as $row) {
            foreach ($row['periods'] as $period) {
                if (!$period['off_start'] || $period['cell_error']) {
                    continue;
                }
                $off = Carbon::parse($period['off_start'])->startOfDay();
                $pair = (string) $row['nik'] . '|' . $off->toDateString();
                $entries[$pair] = [
                    'pair' => $pair, 'nik' => (string) $row['nik'], 'ktp' => (string) $row['no_ktp'],
                    'year' => (int) $period['year'], 'period_number' => (int) $period['period_number'],
                    'off_start' => $off->toDateString(), 'off' => $off, 'raw_remark' => $period['raw_remark'],
                    'row_number' => (int) $row['row_number'], 'source_column' => $period['source_column'],
                    'remark_column' => $period['remark_column'],
                ];
            }
        }
        return array_values($entries);
    }

    private function existingSchedules(array $entries): Collection
    {
        $result = collect();
        foreach (array_chunk($entries, self::QUERY_CHUNK_SIZE) as $chunk) {
            $result = $result->merge(RosterSchedule::query()->where(function ($query) use ($chunk) {
                foreach ($chunk as $entry) {
                    $query->orWhere(function ($pair) use ($entry) {
                        $pair->where('employee_nik', $entry['nik'])->whereDate('off_start', $entry['off_start']);
                    });
                }
            })->lockForUpdate()->get());
        }
        return $result->keyBy(fn (RosterSchedule $schedule) => $schedule->employee_nik . '|' . $schedule->off_start->toDateString());
    }

    private function existingHistories(Collection $schedules): Collection
    {
        $ids = $schedules->pluck('id')->filter()->values()->all();
        if ($ids === []) {
            return collect();
        }
        $histories = collect();
        foreach (array_chunk($ids, self::QUERY_CHUNK_SIZE) as $chunk) {
            $histories = $histories->merge(RosterScheduleHistory::query()->whereIn('roster_schedule_id', $chunk)->lockForUpdate()->get());
        }
        return $histories->keyBy(fn (RosterScheduleHistory $history) =>
            $history->employee_nik . '|' . $history->period_year . '|' . $history->period_number . '|' .
            $history->scheduled_off_start->toDateString() . '|' . $history->source_file
        );
    }

    private function historyKey(array $entry): string
    {
        return $entry['nik'] . '|' . $entry['year'] . '|' . $entry['period_number'] . '|' . $entry['off_start'] . '|source.xlsx';
    }

    private function preflightPath(ImportHistory $history, array $statuses): string
    {
        if ($history->import_type !== ImportHistory::TYPE_ROSTER_SCHEDULE || !in_array($history->status, $statuses, true)
            || !$history->expires_at?->isFuture() || !str_starts_with((string) $history->file_path, 'roster-imports/')
            || str_contains((string) $history->file_path, '..')) {
            throw new RuntimeException('Import roster tidak valid untuk diproses.');
        }
        $path = 'private/' . $history->file_path;
        if (!Storage::disk('local')->exists($path)) {
            throw new RuntimeException('Sumber import roster tidak tersedia.');
        }
        $absolutePath = Storage::disk('local')->path($path);
        if (!hash_equals((string) $history->file_checksum, hash_file('sha256', $absolutePath))) {
            throw new RuntimeException('Sumber import roster berubah.');
        }
        return $absolutePath;
    }

    private function emptyResult(): array
    {
        return ['employees' => 0, 'history_created' => 0, 'history_updated' => 0, 'unchanged' => 0,
            'future_generated' => 0, 'need_review' => 0, 'late_candidate_schedule_ids' => []];
    }

    private function publicSummary(array $result): array
    {
        return collect($result)->except('late_candidate_schedule_ids')->map(fn ($value) => (int) $value)->all();
    }

    private function realization(string $classification): string
    {
        return match ($classification) {
            RosterScheduleHistory::CLASSIFICATION_CUTI => RosterSchedule::REALIZATION_CUTI,
            RosterScheduleHistory::CLASSIFICATION_INSENTIF => RosterSchedule::REALIZATION_INSENTIF,
            default => RosterSchedule::REALIZATION_PENDING,
        };
    }

    private function workDays(): int { return max(1, (int) config('roster.work_weeks', 10)) * 7; }
    private function offDays(): int { return max(1, (int) config('roster.off_weeks', 2)) * 7; }
}
