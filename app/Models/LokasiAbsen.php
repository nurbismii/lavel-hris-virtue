<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LokasiAbsen extends Model
{
    protected $table = 'lokasi_absens';

    protected $guarded = [];

    public function divisi()
    {
        return $this->belongsTo(Divisi::class, 'divisi_id');
    }
}
