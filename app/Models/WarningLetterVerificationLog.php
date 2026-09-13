<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarningLetterVerificationLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'accessed_at' => 'datetime',
    ];

    public function warningLetterRequest()
    {
        return $this->belongsTo(WarningLetterRequest::class);
    }
}
