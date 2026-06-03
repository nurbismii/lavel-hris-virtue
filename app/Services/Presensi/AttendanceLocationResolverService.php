<?php

namespace App\Services\Presensi;

use App\Models\Employee;
use App\Models\EmployeeAttendanceLocationAssignment;
use App\Models\LokasiAbsen;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AttendanceLocationResolverService
{
    public function resolveForEmployee(?Employee $employee, $date = null): ?LokasiAbsen
    {
        return $this->resolveAllForEmployee($employee, $date)->first();
    }

    public function resolveAllForEmployee(?Employee $employee, $date = null): Collection
    {
        if (!$employee || blank($employee->nik)) {
            return collect();
        }

        $dateString = Carbon::parse($date ?: now())->toDateString();
        $assignments = $this->activeAssignmentsFor($employee, $dateString);

        if ($assignments->isNotEmpty()) {
            $assignmentLocations = $assignments
                ->filter(fn(EmployeeAttendanceLocationAssignment $assignment) => $assignment->location)
                ->unique('lokasi_absen_id')
                ->map(function (EmployeeAttendanceLocationAssignment $assignment) {
                    $assignment->location->setAttribute('attendance_location_source', 'employee_assignment');
                    $assignment->location->setAttribute('attendance_location_assignment_id', $assignment->id);

                    return $assignment->location;
                })
                ->values();

            if ($this->shouldIncludeDivisionDefaults($assignments)) {
                return $assignmentLocations
                    ->merge($this->divisionDefaultLocations($employee))
                    ->unique('id')
                    ->values();
            }

            return $assignmentLocations;
        }

        return $this->divisionDefaultLocations($employee);
    }

    private function shouldIncludeDivisionDefaults(Collection $assignments): bool
    {
        return $assignments->every(function (EmployeeAttendanceLocationAssignment $assignment) {
            return (string) ($assignment->assignment_mode ?? '') === 'append';
        });
    }

    private function divisionDefaultLocations(Employee $employee): Collection
    {
        if (blank($employee->divisi_id)) {
            return collect();
        }

        $locationQuery = LokasiAbsen::query()
            ->where('divisi_id', $employee->divisi_id)
            ->orderBy('id');

        if (Schema::hasTable('divisis') && Schema::hasTable('departemens')) {
            $locationQuery->with('divisi.departemen');
        }

        return $locationQuery
            ->get()
            ->map(function (LokasiAbsen $location) {
                $location->setAttribute('attendance_location_source', 'division_default');

                return $location;
            });
    }

    private function activeAssignmentsFor(Employee $employee, string $date): Collection
    {
        if (!Schema::hasTable('employee_attendance_location_assignments')) {
            return collect();
        }

        $query = EmployeeAttendanceLocationAssignment::query()
            ->where('employee_nik', $employee->nik)
            ->activeAt($date)
            ->orderByDesc('effective_from')
            ->orderByDesc('id');

        if (Schema::hasTable('divisis') && Schema::hasTable('departemens')) {
            $query->with('location.divisi.departemen');
        } else {
            $query->with('location');
        }

        return $query->get();
    }
}
