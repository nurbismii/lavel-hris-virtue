<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeAttendanceSetting extends Model
{
    protected $table = 'employee_attendance_settings';

    protected $guarded = [];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
    ];

    public const STATUS_OFF = 'OFF';
    public const STATUS_HADIR = 'HADIR';

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'nik');
    }
}
