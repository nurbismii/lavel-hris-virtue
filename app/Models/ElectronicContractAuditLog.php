<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectronicContractAuditLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function contract()
    {
        return $this->belongsTo(EmployeeContract::class, 'employee_contract_id');
    }
}
