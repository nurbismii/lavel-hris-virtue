<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceCorrection extends Model
{
    public const STATUS_PENDING = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;

    protected $guarded = [];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'requested_jam_masuk' => 'datetime',
        'requested_jam_istirahat' => 'datetime',
        'requested_jam_kembali_istirahat' => 'datetime',
        'requested_jam_pulang' => 'datetime',
        'change_status_presensi' => 'boolean',
        'old_values' => 'array',
        'applied_values' => 'array',
        'hod_processed_at' => 'datetime',
        'hrd_processed_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'nik_karyawan', 'nik')->select([
            'nik',
            'nama_karyawan',
            'area_kerja',
            'departemen_id',
            'divisi_id',
        ]);
    }

    public function presensi()
    {
        return $this->belongsTo(Presensi::class, 'presensi_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hodProcessor()
    {
        return $this->belongsTo(User::class, 'hod_processed_by');
    }

    public function hrdProcessor()
    {
        return $this->belongsTo(User::class, 'hrd_processed_by');
    }

    public function appliedBy()
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public static function statusPresensiOptions(): array
    {
        return [
            'Cuti Tahunan' => 'Cuti Tahunan',
            'Cuti Roster' => 'Cuti Roster',
            'Izin Berbayar' => 'Izin Berbayar',
            'Izin Tidak Berbayar' => 'Izin Tidak Berbayar',
            'Libur Nasional' => 'Libur Nasional',
            'Off' => 'Off',
        ];
    }

    public static function statusLabel(?int $status): string
    {
        switch ((int) $status) {
            case self::STATUS_APPROVED:
                return 'Disetujui';
            case self::STATUS_REJECTED:
                return 'Ditolak';
            default:
                return 'Menunggu';
        }
    }

    public static function statusBadgeClass(?int $status): string
    {
        switch ((int) $status) {
            case self::STATUS_APPROVED:
                return 'bg-success';
            case self::STATUS_REJECTED:
                return 'bg-danger';
            default:
                return 'bg-warning text-dark';
        }
    }

    public function getHodStatusLabelAttribute(): string
    {
        return self::statusLabel($this->status_hod);
    }

    public function getHrdStatusLabelAttribute(): string
    {
        return self::statusLabel($this->status_hrd);
    }

    public function getOverallStatusLabelAttribute(): string
    {
        if ((int) $this->status_hod === self::STATUS_REJECTED) {
            return 'Ditolak HOD';
        }

        if ((int) $this->status_hod === self::STATUS_PENDING) {
            return 'Menunggu HOD';
        }

        if ((int) $this->status_hrd === self::STATUS_REJECTED) {
            return 'Ditolak HR';
        }

        if ((int) $this->status_hrd === self::STATUS_PENDING) {
            return 'Menunggu HR';
        }

        return 'Selesai';
    }

    public function getOverallBadgeClassAttribute(): string
    {
        if ((int) $this->status_hod === self::STATUS_REJECTED || (int) $this->status_hrd === self::STATUS_REJECTED) {
            return 'bg-danger';
        }

        if ((int) $this->status_hod === self::STATUS_APPROVED && (int) $this->status_hrd === self::STATUS_APPROVED) {
            return 'bg-success';
        }

        return 'bg-warning text-dark';
    }

    public function requestedChanges(): array
    {
        $changes = [];

        foreach ([
            'jam_masuk' => 'Jam Masuk',
            'jam_istirahat' => 'Jam Istirahat',
            'jam_kembali_istirahat' => 'Jam Kembali Istirahat',
            'jam_pulang' => 'Jam Pulang',
        ] as $column => $label) {
            $attribute = 'requested_' . $column;

            if ($this->{$attribute}) {
                $changes[$label] = $this->{$attribute}->format('H:i');
            }
        }

        if ($this->change_status_presensi) {
            $changes['Status Presensi'] = $this->requested_status_presensi ?: 'Hadir normal';
        }

        return $changes;
    }
}
