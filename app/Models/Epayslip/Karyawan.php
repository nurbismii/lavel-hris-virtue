<?php

namespace App\Models\Epayslip;

use Illuminate\Database\Eloquent\Model;

class Karyawan extends Model
{
    protected $connection = 'epayslip';
    protected $table = 'data_karyawans';

    protected $guarded = [];

    public function komponenGaji()
    {
        return $this->hasMany(KomponenGaji::class, 'data_karyawan_id');
    }
}
