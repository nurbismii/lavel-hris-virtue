<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeSalaryHistory extends Model
{
    protected $guarded = [];

    protected $casts = [
        'effective_from' => 'date:Y-m-d',
        'basic_salary' => 'decimal:2',
        'fixed_allowance' => 'decimal:2',
        'non_fixed_allowance' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_nik', 'nik');
    }
}
