<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeCvCertification extends Model
{
    protected $guarded = [];
    protected $casts = ['is_lifetime' => 'boolean', 'source_updated_at' => 'datetime', 'synced_at' => 'datetime'];
}
