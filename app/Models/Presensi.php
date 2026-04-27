<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Presensi extends Model
{
    public const STATUS_ABSEN_VERIFIED = 'verified';
    public const STATUS_ABSEN_PENDING_REVIEW = 'pending_review';
    public const STATUS_ABSEN_REJECTED = 'rejected';

    protected $table = 'absensis';

    protected $guarded = [];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'jam_masuk' => 'datetime',
        'jam_istirahat' => 'datetime',
        'jam_kembali_istirahat' => 'datetime',
        'jam_pulang' => 'datetime',
        'face_verified' => 'boolean',
        'face_verified_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'nik_karyawan')->select('nik', 'divisi_id');
    }

    public static function shortStatus(?string $status): ?string
    {
        switch ($status) {
            case 'Alpa':
                return 'A';
            case 'Libur Nasional':
                return 'L';
            case 'Off':
                return 'OFF';
            case 'Izin Tidak Berbayar':
                return 'I/U';
            case 'Izin Berbayar':
                return 'I/P';
            case 'Cuti Tahunan':
                return 'CT';
            case 'Cuti Roster':
                return 'CR';
            default:
                return $status;
        }
    }

    public static function statusAbsenLabel(?string $status): string
    {
        switch ($status) {
            case self::STATUS_ABSEN_VERIFIED:
                return 'Terverifikasi';
            case self::STATUS_ABSEN_PENDING_REVIEW:
                return 'Review';
            case self::STATUS_ABSEN_REJECTED:
                return 'Ditolak';
            default:
                return 'Belum diverifikasi';
        }
    }

    public static function statusAbsenBadgeClass(?string $status): string
    {
        switch ($status) {
            case self::STATUS_ABSEN_VERIFIED:
                return 'bg-success';
            case self::STATUS_ABSEN_PENDING_REVIEW:
                return 'bg-warning text-dark';
            case self::STATUS_ABSEN_REJECTED:
                return 'bg-danger';
            default:
                return 'bg-secondary';
        }
    }
}
