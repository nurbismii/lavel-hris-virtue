<?php

namespace App\Services\Roster;

use App\Models\Employee;
use App\Models\ImportHistory;
use App\Models\RosterSchedule;
use App\Models\RosterScheduleHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class RosterScheduleImportCommitService
{
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

        return DB::transaction(function () use ($history, $path, $data): array {
            $lockedHistory = ImportHistory::query()->lockForUpdate()->find($history->id);
            if (!$lockedHistory || $lockedHistory->import_type !== ImportHistory::TYPE_ROSTER_SCHEDULE
                || $lockedHistory->status !== ImportHistory::STATUS_PROCESSING
                || !$lockedHistory->expires_at?->isFuture()
                || !hash_equals((string) $lockedHistory->file_checksum, hash_file('sha256', $path))) {
                throw new RuntimeException('Import roster tidak valid untuk diproses.');
            }

            $validation = $this->validator->validate($data);
            if (!$validation['is_valid']) {
                throw new RuntimeException('Workbook roster tidak lagi valid untuk diproses.');
            }

            $niks = $data->rows->pluck('nik')->filter()->unique()->values()->all();
            $employees = Employee::query()->whereIn('nik', $niks)->lockForUpdate()->get()->keyBy('nik');
            if ($employees->count() !== count($niks)) {
                throw new RuntimeException('Identitas karyawan berubah sebelum import diproses.');
            }

            $result = $this->emptyResult();
            $processedNiks = [];

            foreach ($data->rows as $row) {
                $employee = $employees->get($row['nik']);
                if (!$employee || !hash_equals((string) $employee->no_ktp, (string) $row['no_ktp'])) {
                    throw new RuntimeException('Identitas karyawan berubah sebelum import diproses.');
                }

                $processedNiks[(string) $employee->nik] = true;
                foreach ($row['periods'] as $period) {
                    if (!$period['off_start'] || $period['cell_error']) {
                        throw new RuntimeException('Workbook roster tidak lagi valid untuk diproses.');
                    }

                    $existing = RosterSchedule::query()
                        ->where('employee_nik', $employee->nik)
                        ->whereDate('off_start', $period['off_start'])
                        ->lockForUpdate()
                        ->first();
                    if ($existing && $existing->source === RosterSchedule::SOURCE_MANUAL) {
                        throw new RuntimeException('Jadwal manual tidak dapat ditimpa.');
                    }

                    $offStart = Carbon::parse($period['off_start'])->startOfDay();
                    $remark = $this->remarkParser->parse($period['raw_remark'], (int) $period['period_number']);
                    $scheduleValues = [
                        'period_year' => (int) $period['year'],
                        'period_number' => (int) $period['period_number'],
                        'work_start' => $offStart->copy()->subDays($this->workDays())->toDateString(),
                        'work_end' => $offStart->copy()->subDay()->toDateString(),
                        'off_end' => $offStart->copy()->addDays($this->offDays() - 1)->toDateString(),
                        'earned_off_days' => max(0, (int) config('roster.earned_off_days', 5)),
                        'realization_type' => $this->realization($remark['classification']),
                        'source' => RosterSchedule::SOURCE_IMPORT,
                        'notes' => $period['raw_remark'],
                        'is_active' => $employee->status_resign === 'AKTIF',
                        'updated_by' => $lockedHistory->confirmed_by,
                    ];

                    if ($existing) {
                        $previousRealization = $existing->realization_type;
                        $existing->fill($scheduleValues)->save();
                        $schedule = $existing;
                        $result['unchanged']++;
                    } else {
                        $schedule = RosterSchedule::query()->create(array_merge($scheduleValues, [
                            'employee_nik' => $employee->nik,
                            'off_start' => $offStart->toDateString(),
                            'created_by' => $lockedHistory->confirmed_by,
                        ]));
                    }

                    $historyRecord = RosterScheduleHistory::query()->firstOrNew([
                        'employee_nik' => $employee->nik,
                        'period_year' => (int) $period['year'],
                        'period_number' => (int) $period['period_number'],
                        'scheduled_off_start' => $offStart->toDateString(),
                        'source_file' => 'source.xlsx',
                    ]);
                    $wasHistory = $historyRecord->exists;
                    $historyValues = [
                        'roster_schedule_id' => $schedule->id,
                        'scheduled_off_end' => $offStart->copy()->addDays($this->offDays() - 1)->toDateString(),
                        'classification' => $remark['classification'],
                        'review_status' => $remark['review_status'],
                        'remark_segment' => $remark['remark_segment'],
                        'raw_remark' => $period['raw_remark'],
                        'source_sheet' => $data->sheetName,
                        'source_row' => $row['row_number'],
                        'source_column' => $period['source_column'],
                        'source_remark_column' => $period['remark_column'],
                        'imported_at' => now(),
                        'imported_by' => $lockedHistory->confirmed_by,
                    ];
                    if ($wasHistory && $historyRecord->review_status === RosterScheduleHistory::REVIEW_CONFIRMED) {
                        unset($historyValues['classification'], $historyValues['review_status']);
                        if ($existing) {
                            $schedule->update(['realization_type' => $previousRealization]);
                        }
                    }
                    $historyRecord->fill($historyValues)->save();
                    $result[$wasHistory ? 'history_updated' : 'history_created']++;
                    if ($historyRecord->review_status === RosterScheduleHistory::REVIEW_PENDING) {
                        $result['need_review']++;
                    }
                    if ($offStart->betweenIncluded(Carbon::today(), Carbon::today()->addDays(13))) {
                        $result['late_candidate_schedule_ids'][] = $schedule->id;
                    }
                }
            }

            foreach (array_keys($processedNiks) as $nik) {
                $this->rosterService->synchronizeSequence($nik);
                $employee = $employees->get($nik);
                if ($employee->status_resign === 'AKTIF') {
                    $before = RosterSchedule::query()->where('employee_nik', $nik)->count();
                    $this->rosterService->generateUntil(
                        $employee,
                        now()->addYears(max(1, (int) config('roster.generate_years_ahead', 2)))->endOfYear(),
                        $lockedHistory->confirmed_by
                    );
                    $result['future_generated'] += max(0, RosterSchedule::query()->where('employee_nik', $nik)->count() - $before);
                }
            }

            $result['employees'] = count($processedNiks);
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

    private function preflightPath(ImportHistory $history, array $statuses): string
    {
        if ($history->import_type !== ImportHistory::TYPE_ROSTER_SCHEDULE
            || !in_array($history->status, $statuses, true)
            || !$history->expires_at?->isFuture()
            || !str_starts_with((string) $history->file_path, 'roster-imports/')
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
        return [
            'employees' => 0,
            'history_created' => 0,
            'history_updated' => 0,
            'unchanged' => 0,
            'future_generated' => 0,
            'need_review' => 0,
            'late_candidate_schedule_ids' => [],
        ];
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

    private function workDays(): int
    {
        return max(1, (int) config('roster.work_weeks', 10)) * 7;
    }

    private function offDays(): int
    {
        return max(1, (int) config('roster.off_weeks', 2)) * 7;
    }
}
