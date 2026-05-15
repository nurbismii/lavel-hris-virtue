<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeContract extends Model
{
    public const STATUS_READY = 'ready';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    protected $casts = [
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'first_extension_end_date' => 'date',
        'salary' => 'decimal:2',
        'meal_allowance' => 'decimal:2',
        'signed_at' => 'datetime',
        'first_party_signed_at' => 'datetime',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_READY => 'Menunggu Tanda Tangan',
            self::STATUS_SIGNED => 'Sudah Ditandatangani',
            self::STATUS_CANCELLED => 'Dibatalkan',
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

    public function getTypeLabelAttribute(): string
    {
        return ContractTemplate::typeOptions()[$this->contract_type] ?? $this->contract_type;
    }

    public function getDisplayNumberAttribute(): string
    {
        return $this->addendum_number ?: ($this->contract_number ?: $this->pkwt_number);
    }

    public function isAddendum(): bool
    {
        return $this->contract_type === ContractTemplate::TYPE_ADDENDUM_PKWT;
    }

    public function isReadyForSignature(): bool
    {
        return $this->status === self::STATUS_READY;
    }
}
