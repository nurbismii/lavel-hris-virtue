<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeContractRenewal extends Model
{
    public const STATUS_PENDING_DELEGATION = 'pending_delegation';
    public const STATUS_WAITING_DELEGATE_ASSESSMENT = 'waiting_delegate_assessment';
    public const STATUS_WAITING_HOD_APPROVAL = 'waiting_hod_approval';
    public const STATUS_WAITING_HRD_APPROVAL = 'waiting_hrd_approval';
    public const STATUS_REJECTED_BY_HOD = 'rejected_by_hod';
    public const STATUS_REJECTED_BY_HRD = 'rejected_by_hrd';
    public const STATUS_CONTRACT_GENERATED = 'contract_generated';

    public const APPROVAL_PENDING = 0;
    public const APPROVAL_APPROVED = 1;
    public const APPROVAL_REJECTED = 2;

    protected $guarded = [];

    protected $casts = [
        'current_contract_end_date' => 'date',
        'delegated_at' => 'datetime',
        'assessment_months' => 'integer',
        'assessed_at' => 'datetime',
        'hod_status' => 'integer',
        'hod_approved_at' => 'datetime',
        'hrd_status' => 'integer',
        'hrd_approved_at' => 'datetime',
        'employee_notified_at' => 'datetime',
    ];

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING_DELEGATION => 'Menunggu penilaian/delegasi',
            self::STATUS_WAITING_DELEGATE_ASSESSMENT => 'Menunggu penilaian delegasi',
            self::STATUS_WAITING_HOD_APPROVAL => 'Menunggu approval HOD',
            self::STATUS_WAITING_HRD_APPROVAL => 'Menunggu approval HRD',
            self::STATUS_REJECTED_BY_HOD => 'Ditolak HOD',
            self::STATUS_REJECTED_BY_HRD => 'Ditolak HRD',
            self::STATUS_CONTRACT_GENERATED => 'Kontrak dibuat',
        ];
    }

    public static function statusBadgeClasses(): array
    {
        return [
            self::STATUS_PENDING_DELEGATION => 'secondary',
            self::STATUS_WAITING_DELEGATE_ASSESSMENT => 'info',
            self::STATUS_WAITING_HOD_APPROVAL => 'warning',
            self::STATUS_WAITING_HRD_APPROVAL => 'primary',
            self::STATUS_REJECTED_BY_HOD => 'danger',
            self::STATUS_REJECTED_BY_HRD => 'danger',
            self::STATUS_CONTRACT_GENERATED => 'success',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_nik', 'nik');
    }

    public function currentHistory()
    {
        return $this->belongsTo(EmployeeContractHistory::class, 'current_contract_history_id');
    }

    public function currentContract()
    {
        return $this->belongsTo(EmployeeContract::class, 'current_contract_id');
    }

    public function generatedContract()
    {
        return $this->belongsTo(EmployeeContract::class, 'generated_contract_id');
    }

    public function delegate()
    {
        return $this->belongsTo(User::class, 'delegate_user_id');
    }

    public function delegatedBy()
    {
        return $this->belongsTo(User::class, 'delegated_by_user_id');
    }

    public function assessedBy()
    {
        return $this->belongsTo(User::class, 'assessed_by_user_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return self::statusBadgeClasses()[$this->status] ?? 'secondary';
    }
}
