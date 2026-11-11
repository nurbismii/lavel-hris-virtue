<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollEntry extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['snapshot'=>'array','finalized_at'=>'datetime'];
}
