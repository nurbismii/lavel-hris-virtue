<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Presensi extends Model
{
    protected $table = 'absensis';

    protected $guarded = [];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'nik_karyawan')->select('nik', 'divisi_id');
    }
}
