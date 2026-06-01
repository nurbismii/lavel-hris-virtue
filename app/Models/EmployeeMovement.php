<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeMovement extends Model
{
    public const TYPE_PROMOTION = 'promotion';
    public const TYPE_DEMOTION = 'demotion';
    public const TYPE_MUTATION = 'mutation';

    public const APPROVAL_PENDING = 0;
    public const APPROVAL_APPROVED = 1;
    public const APPROVAL_REJECTED = 2;

    public const STATUS_PENDING_HOD = 'pending_hod';
    public const STATUS_PENDING_HRD = 'pending_hrd';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_APPLIED = 'applied';

    protected $table = 'employee_movements';

    protected $guarded = [];

    protected $casts = [
        'effective_date' => 'date',
        'hod_status' => 'integer',
        'hod_processed_at' => 'datetime',
        'hrd_status' => 'integer',
        'hrd_processed_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public static function typeOptions(): array
    {
        return [
            self::TYPE_PROMOTION => 'Promosi',
            self::TYPE_DEMOTION => 'Demosi',
            self::TYPE_MUTATION => 'Mutasi',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING_HOD => 'Menunggu HOD',
            self::STATUS_PENDING_HRD => 'Menunggu HRD',
            self::STATUS_APPROVED => 'Disetujui & diterapkan',
            self::STATUS_REJECTED => 'Ditolak',
            self::STATUS_CANCELLED => 'Dibatalkan',
            self::STATUS_APPLIED => 'Sudah diterapkan',
        ];
    }

    public static function approvalStatusOptions(): array
    {
        return [
            self::APPROVAL_PENDING => 'Menunggu',
            self::APPROVAL_APPROVED => 'Disetujui',
            self::APPROVAL_REJECTED => 'Ditolak',
        ];
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeOptions()[$this->movement_type] ?? $this->movement_type;
    }

    public function getTypeBadgeClassAttribute(): string
    {
        return [
            self::TYPE_PROMOTION => 'success',
            self::TYPE_DEMOTION => 'warning',
            self::TYPE_MUTATION => 'info',
        ][$this->movement_type] ?? 'secondary';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusOptions()[$this->status] ?? $this->status;
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return [
            self::STATUS_PENDING_HOD => 'warning',
            self::STATUS_PENDING_HRD => 'primary',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_CANCELLED => 'secondary',
            self::STATUS_APPLIED => 'success',
        ][$this->status] ?? 'secondary';
    }

    public function getHodStatusLabelAttribute(): string
    {
        return self::approvalStatusOptions()[(int) $this->hod_status] ?? 'Menunggu';
    }

    public function getHodStatusBadgeClassAttribute(): string
    {
        return $this->approvalBadgeClass((int) $this->hod_status);
    }

    public function getHrdStatusLabelAttribute(): string
    {
        return self::approvalStatusOptions()[(int) $this->hrd_status] ?? 'Menunggu';
    }

    public function getHrdStatusBadgeClassAttribute(): string
    {
        return $this->approvalBadgeClass((int) $this->hrd_status);
    }

    public function isPendingHod(): bool
    {
        return $this->status === self::STATUS_PENDING_HOD
            && (int) $this->hod_status === self::APPROVAL_PENDING;
    }

    public function isPendingHrd(): bool
    {
        return $this->status === self::STATUS_PENDING_HRD
            && (int) $this->hod_status === self::APPROVAL_APPROVED
            && (int) $this->hrd_status === self::APPROVAL_PENDING;
    }

    private function approvalBadgeClass(int $status): string
    {
        return [
            self::APPROVAL_PENDING => 'warning',
            self::APPROVAL_APPROVED => 'success',
            self::APPROVAL_REJECTED => 'danger',
        ][$status] ?? 'secondary';
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_nik', 'nik');
    }

    public function oldDepartemen()
    {
        return $this->belongsTo(Departemen::class, 'old_departemen_id');
    }

    public function newDepartemen()
    {
        return $this->belongsTo(Departemen::class, 'new_departemen_id');
    }

    public function oldDivisi()
    {
        return $this->belongsTo(Divisi::class, 'old_divisi_id');
    }

    public function newDivisi()
    {
        return $this->belongsTo(Divisi::class, 'new_divisi_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function hodProcessor()
    {
        return $this->belongsTo(User::class, 'hod_processed_by', 'id');
    }

    public function hrdProcessor()
    {
        return $this->belongsTo(User::class, 'hrd_processed_by', 'id');
    }

    public function applier()
    {
        return $this->belongsTo(User::class, 'applied_by_user_id', 'id');
    }
}
