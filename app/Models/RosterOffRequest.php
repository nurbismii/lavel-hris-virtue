<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RosterOffRequest extends Model
{
    public const STATUS_PENDING = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;

    protected $guarded = [];

    protected $casts = [
        'tanggal_off' => 'date:Y-m-d',
        'delegate_processed_at' => 'datetime',
        'hod_processed_at' => 'datetime',
        'hrd_processed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'nik_karyawan', 'nik')->select([
            'nik',
            'nama_karyawan',
            'departemen_id',
            'divisi_id',
        ]);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function delegateProcessor()
    {
        return $this->belongsTo(User::class, 'delegate_processed_by');
    }

    public function scopeEffectiveForAttendance(Builder $query): Builder
    {
        return $query
            ->where('status_hod', self::STATUS_APPROVED)
            ->where('status_hrd', self::STATUS_APPROVED);
    }

    public function getStatusHodLabelAttribute(): string
    {
        return $this->statusBadge((int) $this->status_hod);
    }

    public function getStatusHrdLabelAttribute(): string
    {
        return $this->statusBadge((int) $this->status_hrd);
    }

    public function getStatusDelegateLabelAttribute(): string
    {
        if ($this->delegate_status === null) {
            return '<span class="badge bg-secondary">' . e(__('self_service.status.no_delegate')) . '</span>';
        }

        switch ((int) $this->delegate_status) {
            case self::STATUS_APPROVED:
                return '<span class="badge bg-success">' . e(__('self_service.status.accepted_delegate')) . '</span>';
            case self::STATUS_REJECTED:
                return '<span class="badge bg-danger">' . e(__('self_service.status.rejected_delegate')) . '</span>';
            default:
                return '<span class="badge bg-warning text-dark">' . e(__('self_service.status.pending_delegate')) . '</span>';
        }
    }

    public function getCanBeManagedByEmployeeAttribute(): bool
    {
        return (int) $this->status_hod === self::STATUS_PENDING
            && (int) $this->status_hrd === self::STATUS_PENDING
            && ($this->delegate_status === null || (int) $this->delegate_status === self::STATUS_PENDING);
    }

    public static function statusText(?int $status): string
    {
        switch ((int) $status) {
            case self::STATUS_APPROVED:
                return __('self_service.status.accepted');
            case self::STATUS_REJECTED:
                return __('self_service.status.rejected');
            default:
                return __('self_service.status.pending');
        }
    }

    private function statusBadge(int $status): string
    {
        switch ($status) {
            case self::STATUS_APPROVED:
                return '<span class="badge bg-success">' . e(__('self_service.status.accepted')) . '</span>';
            case self::STATUS_REJECTED:
                return '<span class="badge bg-danger">' . e(__('self_service.status.rejected')) . '</span>';
            default:
                return '<span class="badge bg-warning text-dark">' . e(__('self_service.status.pending')) . '</span>';
        }
    }
}
