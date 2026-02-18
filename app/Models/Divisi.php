<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Divisi extends Model
{
    protected $table = 'divisis';

    protected $guarded = [];

    public function departemen()
    {
        return $this->belongsTo(Departemen::class, 'departemen_id');
    }

    public function karyawan()
    {
        return $this->hasMany(Employee::class, 'divisi_id')->where('status_resign', 'AKTIF');
    }
}
