<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeShiftAssignment extends Model
{
    protected $table = 'employee_shift_assignments';

    protected $guarded = [];

    protected $casts = [
        'shift_date' => 'date:Y-m-d',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'nik');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }
}
