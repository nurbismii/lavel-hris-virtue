<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeCvLanguage extends Model
{
    protected $guarded = [];
    protected $casts = ['source_updated_at' => 'datetime', 'synced_at' => 'datetime'];
}
