<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalDelegationAssignment extends Model
{
    protected $table = 'approval_delegation_request_assignments';

    protected $guarded = [];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function delegation()
    {
        return $this->belongsTo(ApprovalDelegation::class, 'approval_delegation_id');
    }

    public function delegate()
    {
        return $this->belongsTo(User::class, 'delegate_user_id');
    }
}
