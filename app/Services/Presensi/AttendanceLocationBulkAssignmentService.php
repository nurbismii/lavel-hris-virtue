<?php

namespace App\Services\Presensi;

use App\Models\Employee;
use App\Models\EmployeeAttendanceLocationAssignment;
use App\Models\LokasiAbsen;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AttendanceLocationBulkAssignmentService
{
    public function candidateQuery(User $actor, array $filters): Builder
    {
        $query = $actor
            ->applyEmployeeScope(Employee::query(), 'employees')
            ->select([
                'employees.nik',
                'employees.nama_karyawan',
                'employees.departemen_id',
                'employees.divisi_id',
                'employees.status_resign',
            ])
            ->with(['departemen.perusahaan', 'divisi']);

        if (filled($filters['perusahaan_id'] ?? null)) {
            $query->whereHas('departemen', function (Builder $departemenQuery) use ($filters) {
                $departemenQuery->where('perusahaan_id', $filters['perusahaan_id']);
            });
        }

        if (filled($filters['departemen_id'] ?? null)) {
            $query->where('employees.departemen_id', $filters['departemen_id']);
        }

        if (filled($filters['divisi_id'] ?? null)) {
            $query->where('employees.divisi_id', $filters['divisi_id']);
        }

        $employeeNiks = $this->normalizeEmployeeNiks($filters['employee_niks'] ?? []);

        if (!empty($employeeNiks)) {
            $query->whereIn('employees.nik', $employeeNiks);
        }

        return $query;
    }

    public function normalizeEmployeeNiks($value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/[\s,;]+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        return collect($items)
            ->map(fn($nik) => trim((string) $nik))
            ->filter(fn($nik) => $nik !== '')
            ->unique()
            ->values()
            ->all();
    }

    public function assignByFilter(
        User $actor,
        LokasiAbsen $location,
        array $filters,
        Carbon $effectiveFrom,
        ?Carbon $effectiveUntil = null,
        ?string $note = null
    ): array {
        $candidateQuery = $this->candidateQuery($actor, $filters)
            ->where('employees.status_resign', 'AKTIF');
        $total = (clone $candidateQuery)->count('employees.nik');

        if ($total < 1) {
            return [
                'assigned_count' => 0,
                'batch_id' => null,
            ];
        }

        $batchId = (string) Str::uuid();
        $fromDate = $effectiveFrom->toDateString();
        $untilDate = $effectiveUntil ? $effectiveUntil->toDateString() : null;
        $previousDate = $effectiveFrom->copy()->subDay()->toDateString();
        $now = now();
        $assignmentSource = !empty($this->normalizeEmployeeNiks($filters['employee_niks'] ?? []))
            ? EmployeeAttendanceLocationAssignment::SOURCE_SELECTED_NIKS
            : EmployeeAttendanceLocationAssignment::SOURCE_BULK_FILTER;

        DB::transaction(function () use (
            $candidateQuery,
            $location,
            $actor,
            $fromDate,
            $untilDate,
            $previousDate,
            $batchId,
            $note,
            $now,
            $assignmentSource
        ) {
            $candidateQuery
                ->orderBy('employees.nik')
                ->chunk(500, function ($employees) use (
                    $location,
                    $actor,
                    $fromDate,
                    $untilDate,
                    $previousDate,
                    $batchId,
                    $note,
                    $now,
                    $assignmentSource
                ) {
                    $niks = $employees->pluck('nik')->values()->all();

                    EmployeeAttendanceLocationAssignment::query()
                        ->whereIn('employee_nik', $niks)
                        ->where('effective_from', '<', $fromDate)
                        ->where(function (Builder $assignmentQuery) use ($fromDate) {
                            $assignmentQuery
                                ->whereNull('effective_until')
                                ->orWhere('effective_until', '>=', $fromDate);
                        })
                        ->update([
                            'effective_until' => $previousDate,
                            'updated_at' => $now,
                        ]);

                    $rows = $employees->map(function (Employee $employee) use (
                        $location,
                        $actor,
                        $fromDate,
                        $untilDate,
                        $batchId,
                        $note,
                        $now,
                        $assignmentSource
                    ) {
                        return [
                            'employee_nik' => $employee->nik,
                            'lokasi_absen_id' => $location->id,
                            'effective_from' => $fromDate,
                            'effective_until' => $untilDate,
                            'assigned_by' => (string) $actor->id,
                            'batch_id' => $batchId,
                            'assignment_source' => $assignmentSource,
                            'note' => $note,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    })->all();

                    DB::table('employee_attendance_location_assignments')->upsert(
                        $rows,
                        ['employee_nik', 'effective_from'],
                        [
                            'lokasi_absen_id',
                            'effective_until',
                            'assigned_by',
                            'batch_id',
                            'assignment_source',
                            'note',
                            'updated_at',
                        ]
                    );
                });
        });

        app(AuditTrailService::class)->record([
            'event' => 'attendance_location.bulk_assigned',
            'module' => 'attendance_location',
            'reference_table' => 'lokasi_absens',
            'reference_id' => (string) $location->id,
            'actor' => $actor,
            'new_values' => [
                'lokasi_absen_id' => $location->id,
                'effective_from' => $fromDate,
                'effective_until' => $untilDate,
                'assigned_count' => $total,
            ],
            'metadata' => [
                'batch_id' => $batchId,
                'filters' => $filters,
                'assignment_source' => $assignmentSource,
            ],
            'note' => $note,
        ]);

        return [
            'assigned_count' => $total,
            'batch_id' => $batchId,
        ];
    }
}
