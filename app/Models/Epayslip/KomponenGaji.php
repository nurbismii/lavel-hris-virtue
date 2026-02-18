<?php

namespace App\Models\Epayslip;

use Illuminate\Database\Eloquent\Model;

class KomponenGaji extends Model
{
    protected $connection = 'epayslip';
    protected $table = 'komponen_gajis';

    protected $guarded = [];

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class, 'data_karyawan_id', 'id')->select('id', 'no_ktp', 'nama', 'nik', 'nm_perusahaan', 'bpjs_ket', 'bpjs_tk', 'npwp');
    }
}
