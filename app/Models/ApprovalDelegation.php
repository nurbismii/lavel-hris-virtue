<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalDelegation extends Model
{
    public const MODULE_ALL = 'all';
    public const MODULE_CUTI = 'cuti';
    public const MODULE_IZIN = 'izin';
    public const MODULE_ROSTER = 'roster';
    public const MODULE_ROSTER_OFF = 'roster_off';
    public const MODULE_ATTENDANCE_CORRECTION = 'attendance_correction';
    public const MODULE_EMPLOYEE_MOVEMENT = 'employee_movement';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function moduleLabels(): array
    {
        return [
            self::MODULE_ALL => 'Semua Modul',
            self::MODULE_CUTI => 'Cuti Tahunan',
            self::MODULE_IZIN => 'Izin Paid/Unpaid',
            self::MODULE_ROSTER => 'Cuti/Insentif Roster',
            self::MODULE_ROSTER_OFF => 'OFF Roster',
            self::MODULE_ATTENDANCE_CORRECTION => 'Koreksi Presensi',
            self::MODULE_EMPLOYEE_MOVEMENT => 'Perubahan posisi',
        ];
    }

    public function hod()
    {
        return $this->belongsTo(User::class, 'hod_user_id');
    }

    public function delegate()
    {
        return $this->belongsTo(User::class, 'delegate_user_id');
    }

    public function departemen()
    {
        return $this->belongsTo(Departemen::class, 'departemen_id');
    }

    public function divisi()
    {
        return $this->belongsTo(Divisi::class, 'divisi_id');
    }

    public function getModuleLabelAttribute(): string
    {
        return self::moduleLabels()[$this->module] ?? $this->module;
    }

    public function getScopeLabelAttribute(): string
    {
        if ($this->divisi) {
            return 'Divisi ' . ($this->divisi->nama_divisi ?? $this->divisi->divisi ?? $this->divisi_id);
        }

        if ($this->departemen) {
            return 'Departemen ' . ($this->departemen->nama_departemen ?? $this->departemen->departemen ?? $this->departemen_id);
        }

        return 'Scope belum lengkap';
    }
}
