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

    public function scopeEffectiveForAttendance(Builder $query): Builder
    {
        return $query
            ->where('status_hod', self::STATUS_APPROVED)
            ->where('status_hrd', '!=', self::STATUS_REJECTED);
    }

    public function getStatusHodLabelAttribute(): string
    {
        return $this->statusBadge((int) $this->status_hod);
    }

    public function getStatusHrdLabelAttribute(): string
    {
        return $this->statusBadge((int) $this->status_hrd);
    }

    public function getCanBeManagedByEmployeeAttribute(): bool
    {
        return (int) $this->status_hod === self::STATUS_PENDING
            && (int) $this->status_hrd === self::STATUS_PENDING;
    }

    public static function statusText(?int $status): string
    {
        switch ((int) $status) {
            case self::STATUS_APPROVED:
                return 'Diterima';
            case self::STATUS_REJECTED:
                return 'Ditolak';
            default:
                return 'Menunggu';
        }
    }

    private function statusBadge(int $status): string
    {
        switch ($status) {
            case self::STATUS_APPROVED:
                return '<span class="badge bg-success">Diterima</span>';
            case self::STATUS_REJECTED:
                return '<span class="badge bg-danger">Ditolak</span>';
            default:
                return '<span class="badge bg-warning text-dark">Menunggu</span>';
        }
    }
}
