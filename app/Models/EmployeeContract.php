<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeContract extends Model
{
    public const STATUS_READY = 'ready';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REJECTED = 'rejected';

    public const SIGNING_METHOD_ELECTRONIC = 'electronic';
    public const SIGNING_METHOD_MANUAL = 'manual';

    public const SIGNATURE_STATUS_DRAFT = 'draft';
    public const SIGNATURE_STATUS_WAITING = 'waiting_signature';
    public const SIGNATURE_STATUS_SIGNED = 'signed';
    public const SIGNATURE_STATUS_REJECTED = 'rejected';
    public const SIGNATURE_STATUS_CANCELLED = 'cancelled';

    public const MANUAL_VERIFICATION_PENDING = 'pending_review';
    public const MANUAL_VERIFICATION_VERIFIED = 'verified';
    public const MANUAL_VERIFICATION_REJECTED = 'rejected';

    protected $guarded = [];

    protected $casts = [
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'first_extension_end_date' => 'date',
        'salary' => 'decimal:2',
        'meal_allowance' => 'decimal:2',
        'signed_at' => 'datetime',
        'first_party_signed_at' => 'datetime',
        'manual_uploaded_at' => 'datetime',
        'vhire_contract_synced_at' => 'datetime',
        'vhire_activation_synced_at' => 'datetime',
        'visible_in_vhire' => 'boolean',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_READY => __('self_service.contract.statuses.ready'),
            self::STATUS_SIGNED => __('self_service.contract.statuses.signed'),
            self::STATUS_CANCELLED => __('self_service.contract.statuses.cancelled'),
            self::STATUS_REJECTED => __('self_service.contract.statuses.rejected'),
        ];
    }

    public static function signingMethodOptions(): array
    {
        return [
            self::SIGNING_METHOD_ELECTRONIC => __('self_service.contract.signing_methods.electronic'),
            self::SIGNING_METHOD_MANUAL => __('self_service.contract.signing_methods.manual'),
        ];
    }

    public static function signatureStatusOptions(): array
    {
        return [
            self::SIGNATURE_STATUS_DRAFT => __('self_service.contract.statuses.draft'),
            self::SIGNATURE_STATUS_WAITING => __('self_service.contract.statuses.ready'),
            self::SIGNATURE_STATUS_SIGNED => __('self_service.contract.statuses.signed'),
            self::SIGNATURE_STATUS_REJECTED => __('self_service.contract.statuses.rejected'),
            self::SIGNATURE_STATUS_CANCELLED => __('self_service.contract.statuses.cancelled'),
        ];
    }

    public static function manualVerificationStatusOptions(): array
    {
        return [
            self::MANUAL_VERIFICATION_PENDING => __('self_service.contract.statuses.pending_review'),
            self::MANUAL_VERIFICATION_VERIFIED => __('self_service.contract.statuses.verified'),
            self::MANUAL_VERIFICATION_REJECTED => __('self_service.contract.statuses.rejected'),
        ];
    }

    public function template()
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'nik', 'nik');
    }

    public function onboardingCandidate()
    {
        return $this->belongsTo(OnboardingCandidate::class, 'onboarding_candidate_id');
    }

    public function signature()
    {
        return $this->hasOne(EmployeeContractSignature::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(ElectronicContractAuditLog::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusOptions()[$this->status] ?? $this->status;
    }

    public function getSigningMethodLabelAttribute(): string
    {
        return self::signingMethodOptions()[$this->signing_method] ?? ($this->signing_method ?: '-');
    }

    public function getSignatureStatusLabelAttribute(): string
    {
        return self::signatureStatusOptions()[$this->signature_status] ?? ($this->signature_status ?: '-');
    }

    public function getManualVerificationStatusLabelAttribute(): string
    {
        return self::manualVerificationStatusOptions()[$this->manual_verification_status] ?? ($this->manual_verification_status ?: '-');
    }

    public function getTypeLabelAttribute(): string
    {
        return ContractTemplate::typeOptions()[$this->contract_type] ?? $this->contract_type;
    }

    public function getDisplayNumberAttribute(): string
    {
        return $this->addendum_number ?: ($this->contract_number ?: $this->pkwt_number);
    }

    public function getDisplayEmployeeNameAttribute(): string
    {
        return optional($this->employee)->nama_karyawan
            ?: $this->candidate_name
            ?: optional($this->onboardingCandidate)->nama
            ?: '-';
    }

    public function getMaskedNoKtpAttribute(): string
    {
        $value = (string) ($this->no_ktp ?: optional($this->onboardingCandidate)->no_ktp);

        if ($value === '') {
            return '-';
        }

        if (strlen($value) < 8) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 4) . str_repeat('*', max(strlen($value) - 8, 0)) . substr($value, -4);
    }

    public function isAddendum(): bool
    {
        return $this->contract_type === ContractTemplate::TYPE_ADDENDUM_PKWT;
    }

    public function isReadyForSignature(): bool
    {
        return $this->status === self::STATUS_READY
            && $this->signing_method === self::SIGNING_METHOD_ELECTRONIC;
    }

    public function isManualSigning(): bool
    {
        return $this->signing_method === self::SIGNING_METHOD_MANUAL;
    }

    public function isElectronicSigning(): bool
    {
        return $this->signing_method === self::SIGNING_METHOD_ELECTRONIC;
    }
}
