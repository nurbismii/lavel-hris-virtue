<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobTitleAlias extends Model
{
    protected $guarded = [];

    public function jobTitle()
    {
        return $this->belongsTo(JobTitle::class);
    }
}
