<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmployeeAttendanceLocationAssignment extends Model
{
    protected $table = 'employee_attendance_location_assignments';

    protected $guarded = [];

    protected $casts = [
        'effective_from' => 'date:Y-m-d',
        'effective_until' => 'date:Y-m-d',
    ];

    public const SOURCE_BULK_FILTER = 'bulk_filter';
    public const SOURCE_SELECTED_NIKS = 'selected_niks';

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_nik', 'nik');
    }

    public function location()
    {
        return $this->belongsTo(LokasiAbsen::class, 'lokasi_absen_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by', 'id');
    }

    public function scopeActiveAt(Builder $query, string $date): Builder
    {
        return $query
            ->where('effective_from', '<=', $date)
            ->where(function (Builder $periodQuery) use ($date) {
                $periodQuery
                    ->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $date);
            });
    }
}
