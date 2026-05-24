<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportHistory extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_COMPLETED_WITH_ERRORS = 'completed_with_errors';
    public const STATUS_FAILED = 'failed';

    public const SOURCE_EXCEL = 'excel';
    public const SOURCE_ZIP = 'zip';

    public const TYPE_EMPLOYEE = 'employee';
    public const TYPE_RESIGN = 'resign';
    public const TYPE_SURAT_PERINGATAN = 'surat_peringatan';
    public const TYPE_EMPLOYEE_PHOTO = 'employee_photo';
    public const TYPE_EMPLOYEE_KTP = 'employee_ktp';
    public const TYPE_EMPLOYEE_KK = 'employee_kk';
    public const TYPE_EMPLOYEE_SIM = 'employee_sim';
    public const TYPE_EMPLOYEE_SIO = 'employee_sio';
    public const TYPE_FACE_REFERENCE = 'face_reference';
    public const TYPE_PKWT_ONE_CONTRACT = 'pkwt_one_contract';
    public const TYPE_CONTRACT_HISTORY = 'contract_history';

    protected $guarded = [];

    protected $casts = [
        'file_size' => 'integer',
        'total_rows' => 'integer',
        'success_count' => 'integer',
        'failed_count' => 'integer',
        'skipped_count' => 'integer',
        'inserted_count' => 'integer',
        'updated_count' => 'integer',
        'summary' => 'array',
        'failure_samples' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected $appends = [
        'import_type_label',
        'source_label',
        'status_label',
        'status_badge_class',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function getImportTypeLabelAttribute(): string
    {
        return static::typeLabels()[$this->import_type] ?? $this->import_type;
    }

    public function getSourceLabelAttribute(): string
    {
        return static::sourceLabels()[$this->source] ?? strtoupper((string) $this->source);
    }

    public function getStatusLabelAttribute(): string
    {
        return static::statusLabels()[$this->status] ?? $this->status;
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return static::statusBadgeClasses()[$this->status] ?? 'secondary';
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_QUEUED => 'Menunggu',
            self::STATUS_PROCESSING => 'Diproses',
            self::STATUS_COMPLETED => 'Berhasil',
            self::STATUS_COMPLETED_WITH_ERRORS => 'Berhasil dengan catatan',
            self::STATUS_FAILED => 'Gagal',
        ];
    }

    public static function statusBadgeClasses(): array
    {
        return [
            self::STATUS_QUEUED => 'secondary',
            self::STATUS_PROCESSING => 'info',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_COMPLETED_WITH_ERRORS => 'warning',
            self::STATUS_FAILED => 'danger',
        ];
    }

    public static function sourceLabels(): array
    {
        return [
            self::SOURCE_EXCEL => 'Excel/CSV',
            self::SOURCE_ZIP => 'ZIP',
        ];
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_EMPLOYEE => 'Import Karyawan',
            self::TYPE_RESIGN => 'Import Resign',
            self::TYPE_SURAT_PERINGATAN => 'Import Pelanggaran',
            self::TYPE_EMPLOYEE_PHOTO => 'Import Foto Karyawan',
            self::TYPE_EMPLOYEE_KTP => 'Import KTP',
            self::TYPE_EMPLOYEE_KK => 'Import KK',
            self::TYPE_EMPLOYEE_SIM => 'Import SIM',
            self::TYPE_EMPLOYEE_SIO => 'Import SIO',
            self::TYPE_FACE_REFERENCE => 'Import Foto Referensi Presensi',
            self::TYPE_PKWT_ONE_CONTRACT => 'Import PKWT 1 V-Hire',
            self::TYPE_CONTRACT_HISTORY => 'Import History Kontrak',
        ];
    }

    public static function typeForMedia(string $mediaType): string
    {
        $map = [
            'photo' => self::TYPE_EMPLOYEE_PHOTO,
            'ktp' => self::TYPE_EMPLOYEE_KTP,
            'kk' => self::TYPE_EMPLOYEE_KK,
            'sim' => self::TYPE_EMPLOYEE_SIM,
            'sio' => self::TYPE_EMPLOYEE_SIO,
            'face_reference' => self::TYPE_FACE_REFERENCE,
        ];

        return $map[$mediaType] ?? $mediaType;
    }
}
