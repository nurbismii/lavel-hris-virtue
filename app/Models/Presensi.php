<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Presensi extends Model
{
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
}
