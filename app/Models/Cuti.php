<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cuti extends Model
{
    protected $table = 'cuti_izin';

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
        return $this->belongsTo(Employee::class, 'nik_karyawan')->select('nik', 'nama_karyawan', 'sisa_cuti', 'divisi_id', 'departemen_id');
    }

    public function delegateProcessor()
    {
        return $this->belongsTo(User::class, 'delegate_processed_by');
    }

    public function leaveUsageLedger()
    {
        return $this->hasOne(LeaveBalanceLedger::class, 'reference_id')
            ->where('reference_type', 'cuti_izin')
            ->where('entry_type', LeaveBalanceLedger::TYPE_USAGE);
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
