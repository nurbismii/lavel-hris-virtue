<?php

namespace App\Services\Presensi;

use App\Models\Employee;
use App\Models\EmployeeAttendanceLocationAssignment;
use App\Models\LokasiAbsen;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class AttendanceLocationResolverService
{
    public function resolveForEmployee(?Employee $employee, $date = null): ?LokasiAbsen
    {
        if (!$employee || blank($employee->nik)) {
            return null;
        }

        $dateString = Carbon::parse($date ?: now())->toDateString();
        $assignment = $this->activeAssignmentFor($employee, $dateString);

        if ($assignment && $assignment->location) {
            $assignment->location->setAttribute('attendance_location_source', 'employee_assignment');
            $assignment->location->setAttribute('attendance_location_assignment_id', $assignment->id);

            return $assignment->location;
        }

        if (blank($employee->divisi_id)) {
            return null;
        }

        $locationQuery = LokasiAbsen::query()
            ->where('divisi_id', $employee->divisi_id)
            ->orderBy('id');

        if (Schema::hasTable('divisis') && Schema::hasTable('departemens')) {
            $locationQuery->with('divisi.departemen');
        }

        $location = $locationQuery->first();

        if ($location) {
            $location->setAttribute('attendance_location_source', 'division_default');
        }

        return $location;
    }

    private function activeAssignmentFor(Employee $employee, string $date): ?EmployeeAttendanceLocationAssignment
    {
        if (!Schema::hasTable('employee_attendance_location_assignments')) {
            return null;
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

        return $query->first();
    }
}
