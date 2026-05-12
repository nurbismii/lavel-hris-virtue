<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Roster extends Model
{
    protected $table = 'cuti_roster';

    protected $guarded = [];

    protected $casts = [
        'delegate_processed_at' => 'datetime',
        'hod_processed_at' => 'datetime',
        'hrd_processed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->hasOne(User::class, 'nik_karyawan', 'nik_karyawan');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'nik_karyawan')->select('nik', 'nama_karyawan', 'departemen_id', 'divisi_id');
    }

    public function delegateProcessor()
    {
        return $this->belongsTo(User::class, 'delegate_processed_by');
    }

    public function periodeKerjaRoster()
    {
        return $this->hasOne(PeriodeKerjaRoster::class, 'cuti_roster_id');
    }

    public function getStatusRencanaLabelAttribute()
    {
        $tipe = optional($this->periodeKerjaRoster)->tipe_rencana;

        switch ($tipe) {
            case 1:
                return '<span class="badge bg-success">Roster</span>';
            case 2:
                return '<span class="badge bg-primary">Insentif</span>';
            default:
                return '<span class="badge bg-secondary">-</span>';
        }
    }

    public function getStatusHodLabelAttribute()
    {
        switch ($this->status_pengajuan) {
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

    public function getStatusDelegateLabelAttribute()
    {
        switch ($this->delegate_status) {
            case 0:
                return '<span class="badge bg-warning">Menunggu Delegasi</span>';
            case 1:
                return '<span class="badge bg-success">Diterima Delegasi</span>';
            case 2:
                return '<span class="badge bg-danger">Ditolak Delegasi</span>';
            default:
                return '<span class="badge bg-secondary">Tidak Ada Delegasi</span>';
        }
    }

    public function getStatusHrdLabelAttribute()
    {
        switch ($this->status_pengajuan_hrd) {
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
