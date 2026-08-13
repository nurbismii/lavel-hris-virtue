<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmployeePositionAssignment extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ENDED = 'ended';

    protected $guarded = [];

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_nik', 'nik');
    }

    public function organizationPosition()
    {
        return $this->belongsTo(OrganizationPosition::class);
    }

    public function scopeActiveOn(Builder $query, $date = null): Builder
    {
        $date = $date ?: today();

        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->whereDate('effective_from', '<=', $date)
            ->where(function (Builder $builder) use ($date) {
                $builder->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date);
            });
    }
}
