<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingCandidate extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONTRACT_GENERATED = 'contract_generated';
    public const STATUS_WAITING_SIGNATURE = 'proses_tanda_tangan_kontrak';
    public const STATUS_ACTIVATED = 'activated_as_employee';

    protected $guarded = [];

    protected $casts = [
        'tanggal_mulai_kerja' => 'date',
        'tanggal_akhir_kontrak' => 'date',
        'source_updated_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'activated_as_employee_at' => 'datetime',
        'gaji' => 'decimal:2',
        'uang_makan' => 'decimal:2',
    ];

    public function contracts()
    {
        return $this->hasMany(EmployeeContract::class, 'onboarding_candidate_id');
    }

    public function activeEmployee()
    {
        return $this->belongsTo(Employee::class, 'employee_nik', 'nik');
    }

    public function getMaskedNoKtpAttribute(): string
    {
        $value = (string) $this->no_ktp;

        if (strlen($value) < 8) {
            return $value ? str_repeat('*', strlen($value)) : '-';
        }

        return substr($value, 0, 4) . str_repeat('*', max(strlen($value) - 8, 0)) . substr($value, -4);
    }
}
