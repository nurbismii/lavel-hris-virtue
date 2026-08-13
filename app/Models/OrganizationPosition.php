<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrganizationPosition extends Model
{
    protected $guarded = [];

    protected $casts = [
        'planned_headcount' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    public function perusahaan()
    {
        return $this->belongsTo(Perusahaan::class, 'perusahaan_id');
    }

    public function departemen()
    {
        return $this->belongsTo(Departemen::class, 'departemen_id');
    }

    public function divisi()
    {
        return $this->belongsTo(Divisi::class, 'divisi_id');
    }

    public function jobTitle()
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function levelOverride()
    {
        return $this->belongsTo(JobLevel::class, 'job_level_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_position_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_position_id')->orderBy('sort_order')->orderBy('code');
    }

    public function assignments()
    {
        return $this->hasMany(EmployeePositionAssignment::class);
    }

    public function activeAssignments()
    {
        return $this->assignments()->activeOn();
    }

    public function scopeCurrentlyEffective(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $builder) {
                $builder->whereNull('effective_from')->orWhereDate('effective_from', '<=', today());
            })
            ->where(function (Builder $builder) {
                $builder->whereNull('effective_until')->orWhereDate('effective_until', '>=', today());
            });
    }

    public function getEffectiveLevelAttribute(): ?JobLevel
    {
        return $this->levelOverride ?: optional($this->jobTitle)->level;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->position_name
            ?: (optional($this->jobTitle)->display_name ?: $this->code);
    }
}
