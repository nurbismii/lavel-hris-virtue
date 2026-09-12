<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuratPeringatan extends Model
{
    protected $table = 'sp_report';

    protected $guarded = [];

    public function issuance()
    {
        return $this->hasOne(WarningLetterRequest::class, 'sp_report_id');
    }

    public function employee()
    {
        return $this->hasOne(Employee::class, 'nik', 'nik_karyawan')->select('nik', 'nama_karyawan');
    }
}
