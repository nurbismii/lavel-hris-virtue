<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cuti extends Model
{
    protected $table = 'cuti_izin';

    protected $guarded = [];

    public function user()
    {
        return $this->hasOne(User::class, 'nik_karyawan', 'nik_karyawan');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'nik_karyawan')->select('nik', 'nama_karyawan', 'sisa_cuti', 'divisi_id', 'departemen_id');
    }

    public function getStatusTipeLabelAttribute()
    {
        switch ($this->tipe) {
            case 'PAID':
                return '<span class="badge bg-primary">Paid</span>';
            case 'UNPAID':
                return '<span class="badge bg-warning">Unpaid</span>';
        }
    }

    public function getStatusHodLabelAttribute()
    {
        switch ($this->status_hod) {
            case 0:
                return '<span class="badge bg-warning">Menunggu</span>';
            case 1:
                return '<span class="badge bg-success">Diterima</span>';
            case 2:
                return '<span class="badge bg-danger">Ditolak</span>';
            default:
                return '-';
        }
    }

    public function getStatusHrdLabelAttribute()
    {
        switch ($this->status_hrd) {
            case 0:
                return '<span class="badge bg-warning">Menunggu</span>';
            case 1:
                return '<span class="badge bg-success">Diterima</span>';
            case 2:
                return '<span class="badge bg-danger">Ditolak</span>';
            default:
                return '-';
        }
    }
}
