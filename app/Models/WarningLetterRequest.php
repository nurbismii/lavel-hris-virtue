<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarningLetterRequest extends Model
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const VALIDITY_MONTHS = 6;

    protected $guarded = [];
    protected $hidden = ['letter_snapshot', 'submission_token'];
    protected $casts = [
        'employee_snapshot' => 'array',
        'letter_snapshot' => 'array',
        'reviewed_at' => 'datetime',
        'tgl_mulai' => 'date',
        'tgl_berakhir' => 'date',
    ];

    public static function levels(): array
    {
        return ['SP1' => 'Surat Peringatan 1', 'SP2' => 'Surat Peringatan 2', 'SP3' => 'Surat Peringatan 3'];
    }

    public static function statuses(): array
    {
        return [self::PENDING => 'Menunggu approval', self::APPROVED => 'Disetujui / Terbit', self::REJECTED => 'Ditolak'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'nik', 'nik');
    }
}
