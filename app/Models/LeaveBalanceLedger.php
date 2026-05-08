<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveBalanceLedger extends Model
{
    public const TYPE_ANNUAL_GRANT = 'annual_grant';
    public const TYPE_OPENING_BALANCE = 'opening_balance';
    public const TYPE_CARRY_OVER = 'carry_over';
    public const TYPE_USAGE = 'usage';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_EXPIRED = 'expired';

    public const DIRECTION_CREDIT = 'credit';
    public const DIRECTION_DEBIT = 'debit';

    protected $table = 'leave_balance_ledgers';

    protected $guarded = [];

    protected $casts = [
        'transaction_date' => 'date',
        'effective_date' => 'date',
        'expires_at' => 'date',
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function typeLabels(): array
    {
        return [
            self::TYPE_ANNUAL_GRANT => 'Saldo Tahunan',
            self::TYPE_OPENING_BALANCE => 'Saldo Awal',
            self::TYPE_CARRY_OVER => 'Carry-over',
            self::TYPE_USAGE => 'Pemakaian Cuti',
            self::TYPE_ADJUSTMENT => 'Adjustment HR',
            self::TYPE_EXPIRED => 'Expired',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_nik', 'nik');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeLabels()[$this->entry_type] ?? $this->entry_type;
    }

    public function getSignedAmountAttribute(): float
    {
        $amount = (float) $this->amount;

        return $this->direction === self::DIRECTION_DEBIT ? $amount * -1 : $amount;
    }
}
