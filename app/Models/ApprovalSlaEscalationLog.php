<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalSlaEscalationLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sla_started_at' => 'datetime',
        'due_at' => 'datetime',
        'escalated_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function approvable()
    {
        return $this->morphTo();
    }

    public function escalator()
    {
        return $this->belongsTo(User::class, 'escalated_by');
    }
}
