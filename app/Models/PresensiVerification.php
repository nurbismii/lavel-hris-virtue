<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class PresensiVerification extends Model
{
    public const TYPE_MASUK = 'masuk';
    public const TYPE_ISTIRAHAT = 'istirahat';
    public const TYPE_KEMBALI = 'kembali';
    public const TYPE_PULANG = 'pulang';
    public const REVIEW_APPROVED = 'approved';
    public const REVIEW_REJECTED = 'rejected';

    protected $guarded = [];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'face_verified' => 'boolean',
        'face_verified_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_by' => 'string',
        'reviewed_at' => 'datetime',
    ];

    public function presensi()
    {
        return $this->belongsTo(Presensi::class, 'presensi_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'id');
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

    public static function reviewDecisionLabel(?string $decision): string
    {
        switch ($decision) {
            case self::REVIEW_APPROVED:
                return 'Disetujui HR';
            case self::REVIEW_REJECTED:
                return 'Ditolak HR';
            default:
                return 'Belum diputuskan';
        }
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
