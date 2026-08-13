<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeCvExperience extends Model
{
    protected $guarded = [];
    protected $casts = ['is_current' => 'boolean', 'source_updated_at' => 'datetime', 'synced_at' => 'datetime'];
}
