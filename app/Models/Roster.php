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

    public function schedule()
    {
        return $this->belongsTo(RosterSchedule::class, 'roster_schedule_id');
    }

    public function getStatusRencanaLabelAttribute()
    {
        $tipe = optional($this->periodeKerjaRoster)->tipe_rencana;

        switch ($tipe) {
            case 1:
                return '<span class="badge bg-success">' . e(__('self_service.roster.plan_roster')) . '</span>';
            case 2:
                return '<span class="badge bg-primary">' . e(__('self_service.roster.plan_incentive')) . '</span>';
            default:
                return '<span class="badge bg-secondary">-</span>';
        }
    }

    public function getStatusHodLabelAttribute()
    {
        switch ($this->status_pengajuan) {
            case 0:
                return '<span class="badge bg-warning">' . e(__('self_service.status.pending')) . '</span>';
            case 1:
                return '<span class="badge bg-success">' . e(__('self_service.status.accepted')) . '</span>';
            case 2:
                return '<span class="badge bg-danger">' . e(__('self_service.status.rejected')) . '</span>';
            default:
                return '-';
        }
    }

    public function getStatusDelegateLabelAttribute()
    {
        switch ($this->delegate_status) {
            case 0:
                return '<span class="badge bg-warning">' . e(__('self_service.status.pending_delegate')) . '</span>';
            case 1:
                return '<span class="badge bg-success">' . e(__('self_service.status.accepted_delegate')) . '</span>';
            case 2:
                return '<span class="badge bg-danger">' . e(__('self_service.status.rejected_delegate')) . '</span>';
            default:
                return '<span class="badge bg-secondary">' . e(__('self_service.status.no_delegate')) . '</span>';
        }
    }

    public function getStatusHrdLabelAttribute()
    {
        switch ($this->status_pengajuan_hrd) {
            case 0:
                return '<span class="badge bg-warning">' . e(__('self_service.status.pending')) . '</span>';
            case 1:
                return '<span class="badge bg-success">' . e(__('self_service.status.accepted')) . '</span>';
            case 2:
                return '<span class="badge bg-danger">' . e(__('self_service.status.rejected')) . '</span>';
            default:
                return '-';
        }
    }
}
