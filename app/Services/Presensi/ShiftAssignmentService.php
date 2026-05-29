<?php

namespace App\Services\Presensi;

use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\Shift;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use RuntimeException;

class ShiftAssignmentService
{
    public function getAssignmentsForEmployees($employeeIds, $startDate, $endDate): Collection
    {
        $employeeIds = collect($employeeIds)
            ->filter()
            ->map(fn($employeeId) => (string) $employeeId)
            ->unique()
            ->values();

        if ($employeeIds->isEmpty()) {
            return collect();
        }

        return EmployeeShiftAssignment::query()
            ->with('shift')
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('shift_date', [
                Carbon::parse($startDate)->toDateString(),
                Carbon::parse($endDate)->toDateString(),
            ])
            ->get();
    }

    public function buildAssignmentMap(Collection $employees, $startDate, $endDate, ?Collection $assignments = null): array
    {
        if ($employees->isEmpty()) {
            return [];
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        $assignments = $assignments ?: $this->getAssignmentsForEmployees($employees->pluck('nik'), $startDate, $endDate);
        $assignmentMap = $assignments
            ->groupBy('employee_id')
            ->map(fn(Collection $rows) => $rows->keyBy(fn($row) => Carbon::parse($row->shift_date)->toDateString()));
        $map = [];

        foreach ($employees as $employee) {
            $employeeAssignments = $assignmentMap->get($employee->nik, collect());

            foreach (CarbonPeriod::create($start, $end) as $date) {
                $dateString = $date->toDateString();
                $assignment = $employeeAssignments->get($dateString);

                $map[$employee->nik][$dateString] = [
                    'assignment_id' => optional($assignment)->id,
                    'shift_id' => optional($assignment)->shift_id,
                    'shift' => optional($assignment)->shift,
                ];
            }
        }

        return $map;
    }

    public function resolveShiftForDate(Employee $employee, $date, ?Collection $assignments = null): ?Shift
    {
        $dateString = Carbon::parse($date)->toDateString();

        if ($assignments !== null) {
            return optional(
                $assignments->first(function ($assignment) use ($employee, $dateString) {
                    return (string) $assignment->employee_id === (string) $employee->nik
                        && Carbon::parse($assignment->shift_date)->toDateString() === $dateString;
                })
            )->shift;
        }

        $assignment = EmployeeShiftAssignment::query()
            ->with('shift')
            ->where('employee_id', $employee->nik)
            ->whereDate('shift_date', $dateString)
            ->first();

        return optional($assignment)->shift;
    }

    public function applyAssignment(Employee $employee, $date, $shiftId, ?string $assignedBy = null): void
    {
        $dateString = Carbon::parse($date)->toDateString();
        $periodLockMessage = app(AttendancePeriodLockService::class)->guardDate($dateString, 'Pengaturan shift');

        if ($periodLockMessage) {
            throw new RuntimeException($periodLockMessage);
        }

        if (blank($shiftId)) {
            EmployeeShiftAssignment::query()
                ->where('employee_id', $employee->nik)
                ->whereDate('shift_date', $dateString)
                ->delete();

            return;
        }

        EmployeeShiftAssignment::updateOrCreate(
            [
                'employee_id' => $employee->nik,
                'shift_date' => $dateString,
            ],
            [
                'shift_id' => $shiftId,
                'assigned_by' => $assignedBy,
            ]
        );
    }
}
