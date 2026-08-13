<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobLevel extends Model
{
    protected $guarded = [];

    protected $casts = [
        'rank' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function jobTitles()
    {
        return $this->hasMany(JobTitle::class);
    }

    public function organizationPositions()
    {
        return $this->hasMany(OrganizationPosition::class);
    }
}
