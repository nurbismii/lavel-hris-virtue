<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PresensiVerification extends Model
{
    public const TYPE_MASUK = 'masuk';
    public const TYPE_ISTIRAHAT = 'istirahat';
    public const TYPE_KEMBALI = 'kembali';
    public const TYPE_PULANG = 'pulang';

    protected $guarded = [];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'face_verified' => 'boolean',
        'face_verified_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function presensi()
    {
        return $this->belongsTo(Presensi::class, 'presensi_id');
    }

    public static function attendanceTypes(): array
    {
        return [
            self::TYPE_MASUK,
            self::TYPE_ISTIRAHAT,
            self::TYPE_KEMBALI,
            self::TYPE_PULANG,
        ];
    }

    public static function typeLabel(?string $type): string
    {
        switch ($type) {
            case self::TYPE_MASUK:
                return 'Masuk';
            case self::TYPE_ISTIRAHAT:
                return 'Istirahat';
            case self::TYPE_KEMBALI:
                return 'Kembali';
            case self::TYPE_PULANG:
                return 'Pulang';
            default:
                return 'Presensi';
        }
    }
}
