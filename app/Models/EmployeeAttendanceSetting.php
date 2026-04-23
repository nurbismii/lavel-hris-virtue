<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class EmployeeAttendanceSetting extends Model
{
    protected $table = 'employee_attendance_settings';

    protected $guarded = [];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
    ];

    public const STATUS_OFF = 'OFF';
    public const STATUS_HADIR = 'HADIR';

    protected static $hasStatusColumn;
    protected static $hasPeriodeColumn;

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'nik');
    }

    public static function supportsStatusColumn(): bool
    {
        if (static::$hasStatusColumn === null) {
            static::$hasStatusColumn = Schema::hasColumn((new static)->getTable(), 'status');
        }

        return static::$hasStatusColumn;
    }

    public static function supportsPeriodeColumn(): bool
    {
        if (static::$hasPeriodeColumn === null) {
            static::$hasPeriodeColumn = Schema::hasColumn((new static)->getTable(), 'periode');
        }

        return static::$hasPeriodeColumn;
    }
}
