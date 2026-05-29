<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendancePeriodLock extends Model
{
    public const STATUS_LOCKED = 'locked';
    public const STATUS_REOPENED = 'reopened';

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
        'summary' => 'array',
    ];

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopener()
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function getIsLockedAttribute(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    public function getPeriodLabelAttribute(): string
    {
        if (!$this->start_date || !$this->end_date) {
            return $this->period_key;
        }

        return $this->start_date->format('d M Y') . ' - ' . $this->end_date->format('d M Y');
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->is_locked ? 'Dikunci' : 'Dibuka ulang';
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return $this->is_locked ? 'bg-danger' : 'bg-secondary';
    }
}
