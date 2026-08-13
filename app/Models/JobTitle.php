<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobTitle extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function level()
    {
        return $this->belongsTo(JobLevel::class, 'job_level_id');
    }

    public function aliases()
    {
        return $this->hasMany(JobTitleAlias::class);
    }

    public function organizationPositions()
    {
        return $this->hasMany(OrganizationPosition::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'job_title_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name . (filled($this->name_zh) ? ' ' . $this->name_zh : '');
    }
}
