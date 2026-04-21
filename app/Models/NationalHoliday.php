<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NationalHoliday extends Model
{
    protected $table = 'national_holidays';

    protected $guarded = [];

    protected $casts = [
        'holiday_date' => 'date',
    ];
}
