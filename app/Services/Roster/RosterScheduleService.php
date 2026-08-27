<?php

namespace App\Services\Roster;

use App\Models\Employee;
use App\Models\RosterSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RosterScheduleService
{
    public function generateFromAnchor(
        Employee $employee,
        Carbon $workStart,
        int $cycles,
        ?string $actorId = null,
        string $source = RosterSchedule::SOURCE_GENERATED
    ): Collection {
        $this->assertActiveRosterEmployee($employee);

        $cycles = max(1, min($cycles, 60));
        $generated = collect();
        $cycleDates = $this->previewCycles($workStart, $cycles);

        return DB::transaction(function () use (
            $employee,
            $actorId,
            $source,
            $generated,
            $cycleDates
        ) {
            foreach ($cycleDates as $dates) {
                $cycleWorkStart = $dates['work_start'];
                $workEnd = $dates['work_end'];
                $offStart = $dates['off_start'];
                $offEnd = $dates['off_end'];

                $schedule = RosterSchedule::query()->firstOrCreate(
                    [
                        'employee_nik' => $employee->nik,
                        'off_start' => $offStart->toDateString(),
                    ],
                    [
                        'period_year' => (int) $offStart->year,
                        'period_number' => 1,
                        'work_start' => $cycleWorkStart->toDateString(),
                        'work_end' => $workEnd->toDateString(),
                        'off_end' => $offEnd->toDateString(),
                        'earned_off_days' => max(0, (int) config('roster.earned_off_days', 5)),
                        'realization_type' => RosterSchedule::REALIZATION_PENDING,
                        'source' => $source,
                        'is_active' => true,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]
                );

                $generated->push($schedule);
            }

            $this->synchronizeSequence($employee->nik);

            return $generated->map->fresh();
        });
    }

    public function previewCycles(Carbon $workStart, int $cycles): Collection
    {
        $cycles = max(1, min($cycles, 60));
        $workDays = $this->workDays();
        $cycleDays = $this->cycleDays();

        return collect(range(0, $cycles - 1))->map(function (int $offset) use ($workStart, $workDays, $cycleDays) {
            $cycleWorkStart = $workStart->copy()->addDays($offset * $cycleDays)->startOfDay();
            $workEnd = $cycleWorkStart->copy()->addDays($workDays - 1);
            $offStart = $workEnd->copy()->addDay();

            return [
                'work_start' => $cycleWorkStart,
                'work_end' => $workEnd,
                'off_start' => $offStart,
                'off_end' => $cycleWorkStart->copy()->addDays($cycleDays - 1),
            ];
        });
    }

    public function previewCyclesUntil(Carbon $workStart, Carbon $until): Collection
    {
        $nextOffStart = $workStart->copy()->addDays($this->workDays());

        if ($nextOffStart->gt($until)) {
            return collect();
        }

        $daysFromNextOff = max(0, (int) $nextOffStart->diffInDays($until, false));
        $cycles = intdiv($daysFromNextOff, $this->cycleDays()) + 1;

        return $this->previewCycles($workStart, $cycles);
    }

    public function generateUntil(Employee $employee, Carbon $until, ?string $actorId = null): Collection
    {
        $cycleDays = $this->cycleDays();
        $lastExisting = RosterSchedule::query()
            ->where('employee_nik', $employee->nik)
            ->orderByDesc('off_start')
            ->first();

        if ($lastExisting) {
            $nextWorkStart = $lastExisting->off_end->copy()->addDay();
        } else {
            if (!$employee->work_pattern_start_date) {
                return collect();
            }

            $anchor = Carbon::parse($employee->work_pattern_start_date)->startOfDay();
            $firstOffStart = $anchor->copy()->addDays($this->workDays());

            if ($firstOffStart->gt($until)) {
                return collect();
            }

            $daysToToday = max(0, (int) $firstOffStart->diffInDays(Carbon::today(), false));
            $skipCycles = max(0, intdiv($daysToToday, $cycleDays) - 1);
            $nextWorkStart = $anchor->copy()->addDays($skipCycles * $cycleDays);
        }

        $cycles = $this->previewCyclesUntil($nextWorkStart, $until)->count();

        if ($cycles === 0) {
            return collect();
        }

        return $this->generateFromAnchor($employee, $nextWorkStart, $cycles, $actorId);
    }

    public function updateSchedule(RosterSchedule $schedule, array $data, ?string $actorId = null): RosterSchedule
    {
        return DB::transaction(function () use ($schedule, $data, $actorId) {
            $originalOffStart = $schedule->off_start->copy();
            $workStart = Carbon::parse($data['work_start'])->startOfDay();
            $workEnd = Carbon::parse($data['work_end'])->startOfDay();
            $offStart = Carbon::parse($data['off_start'])->startOfDay();
            $offEnd = Carbon::parse($data['off_end'])->startOfDay();

            if (!$workStart->lte($workEnd) || !$workEnd->lt($offStart) || !$offStart->lte($offEnd)) {
                throw ValidationException::withMessages([
                    'work_start' => 'Urutan tanggal harus: mulai kerja, akhir kerja, mulai off, lalu akhir off.',
                ]);
            }

            $duplicate = RosterSchedule::query()
                ->where('employee_nik', $schedule->employee_nik)
                ->whereDate('off_start', $offStart->toDateString())
                ->whereKeyNot($schedule->getKey())
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'off_start' => 'Jadwal dengan tanggal mulai off tersebut sudah ada untuk karyawan ini.',
                ]);
            }

            $schedule->update([
                'work_start' => $workStart->toDateString(),
                'work_end' => $workEnd->toDateString(),
                'off_start' => $offStart->toDateString(),
                'off_end' => $offEnd->toDateString(),
                'period_year' => (int) $offStart->year,
                'realization_type' => $data['realization_type'],
                'notes' => $data['notes'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? false),
                'source' => RosterSchedule::SOURCE_MANUAL,
                'updated_by' => $actorId,
                'reminder_queued_at' => null,
                'reminder_sent_at' => null,
                'reminder_failed_at' => null,
                'reminder_error' => null,
            ]);

            if (!empty($data['regenerate_following'])) {
                $this->regeneratePendingFollowing($schedule, $originalOffStart, $actorId);
            }

            $this->synchronizeSequence($schedule->employee_nik);

            return $schedule->fresh();
        });
    }

    public function synchronizeSequence(string $employeeNik): void
    {
        $schedules = RosterSchedule::query()
            ->where('employee_nik', $employeeNik)
            ->orderBy('off_start')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $yearCounters = [];

        foreach ($schedules as $index => $schedule) {
            $year = (int) $schedule->off_start->year;
            if ($schedule->source === RosterSchedule::SOURCE_MANUAL) {
                $yearCounters[$year] = max($yearCounters[$year] ?? 0, (int) $schedule->period_number);
                continue;
            }
            if ($schedule->source === RosterSchedule::SOURCE_IMPORT && (int) $schedule->period_number > 0) {
                $periodNumber = (int) $schedule->period_number;
                $yearCounters[$year] = max($yearCounters[$year] ?? 0, $periodNumber);
            } else {
                $periodNumber = ($yearCounters[$year] ?? 0) + 1;
                $yearCounters[$year] = $periodNumber;
            }

            $schedule->forceFill([
                'cycle_number' => $index + 1,
                'period_year' => $year,
                'period_number' => $periodNumber,
            ])->saveQuietly();
        }
    }

    private function regeneratePendingFollowing(RosterSchedule $schedule, Carbon $originalOffStart, ?string $actorId): void
    {
        $nextWorkStart = $schedule->off_end->copy()->addDay();
        $workDays = $this->workDays();
        $cycleDays = $this->cycleDays();

        $following = RosterSchedule::query()
            ->where('employee_nik', $schedule->employee_nik)
            ->whereDate('off_start', '>', $originalOffStart->toDateString())
            ->where('realization_type', RosterSchedule::REALIZATION_PENDING)
            ->orderBy('off_start')
            ->lockForUpdate()
            ->get();

        $updates = $following->values()->map(function (RosterSchedule $item, int $offset) use ($nextWorkStart, $workDays, $cycleDays) {
            $workStart = $nextWorkStart->copy()->addDays($offset * $cycleDays);
            $workEnd = $workStart->copy()->addDays($workDays - 1);
            $offStart = $workEnd->copy()->addDay();
            $offEnd = $workStart->copy()->addDays($cycleDays - 1);

            return compact('item', 'workStart', 'workEnd', 'offStart', 'offEnd');
        });

        if ($updates->isNotEmpty() && $updates->first()['offStart']->gt($updates->first()['item']->off_start)) {
            $updates = $updates->reverse()->values();
        }

        foreach ($updates as $update) {
            $update['item']->update([
                'work_start' => $update['workStart']->toDateString(),
                'work_end' => $update['workEnd']->toDateString(),
                'off_start' => $update['offStart']->toDateString(),
                'off_end' => $update['offEnd']->toDateString(),
                'period_year' => (int) $update['offStart']->year,
                'source' => RosterSchedule::SOURCE_GENERATED,
                'updated_by' => $actorId,
                'reminder_queued_at' => null,
                'reminder_sent_at' => null,
                'reminder_failed_at' => null,
                'reminder_error' => null,
            ]);
        }
    }

    private function assertActiveRosterEmployee(Employee $employee): void
    {
        if ((string) $employee->status_resign !== 'AKTIF') {
            throw ValidationException::withMessages([
                'employee_nik' => 'Jadwal hanya dapat dibuat untuk karyawan aktif.',
            ]);
        }
    }

    private function workDays(): int
    {
        return max(1, (int) config('roster.work_weeks', 10)) * 7;
    }

    private function cycleDays(): int
    {
        return $this->workDays() + (max(1, (int) config('roster.off_weeks', 2)) * 7);
    }
}
