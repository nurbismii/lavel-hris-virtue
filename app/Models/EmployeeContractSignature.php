<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeContractSignature extends Model
{
    protected $guarded = [];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function contract()
    {
        return $this->belongsTo(EmployeeContract::class, 'employee_contract_id');
    }

    public function signer()
    {
        return $this->belongsTo(User::class, 'signed_by_user_id');
    }
}
