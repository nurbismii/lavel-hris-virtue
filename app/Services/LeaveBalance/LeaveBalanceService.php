<?php

namespace App\Services\LeaveBalance;

use App\Exceptions\LeaveBalanceException;
use App\Models\Cuti;
use App\Models\Employee;
use App\Models\LeaveBalanceLedger;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LeaveBalanceService
{
    public function currentBalance(Employee $employee): float
    {
        if (!Schema::hasTable('leave_balance_ledgers')) {
            return (float) $employee->sisa_cuti;
        }

        $latestLedger = LeaveBalanceLedger::query()
            ->where('employee_nik', $employee->nik)
            ->latest('id')
            ->first(['balance_after']);

        return $latestLedger ? (float) $latestLedger->balance_after : (float) $employee->sisa_cuti;
    }

    public function recordManualEntry(Employee $employee, array $data, User $actor): LeaveBalanceLedger
    {
        return DB::transaction(function () use ($employee, $data, $actor) {
            $lockedEmployee = Employee::query()
                ->where('nik', $employee->nik)
                ->lockForUpdate()
                ->firstOrFail();

            return $this->createLedgerEntry($lockedEmployee, [
                'entry_type' => $data['entry_type'],
                'direction' => $this->resolveDirection($data['entry_type'], $data['direction'] ?? null),
                'amount' => $data['amount'],
                'period_year' => $data['period_year'] ?? null,
                'transaction_date' => $data['transaction_date'],
                'effective_date' => $data['effective_date'] ?? $data['transaction_date'],
                'expires_at' => $data['expires_at'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => (string) $actor->id,
            ], $actor);
        });
    }

    public function recordUsageForApprovedCuti(Cuti $cuti, Employee $employee, User $actor): ?LeaveBalanceLedger
    {
        if (!Schema::hasTable('leave_balance_ledgers')) {
            $employee->decrement('sisa_cuti', (int) $cuti->jumlah);
            return null;
        }

        $existingLedger = LeaveBalanceLedger::query()
            ->where('employee_nik', $employee->nik)
            ->where('entry_type', LeaveBalanceLedger::TYPE_USAGE)
            ->where('reference_type', 'cuti_izin')
            ->where('reference_id', (string) $cuti->id)
            ->first();

        if ($existingLedger) {
            return $existingLedger;
        }

        $startDate = $cuti->tanggal_mulai ? Carbon::parse($cuti->tanggal_mulai) : now();
        $endDate = $cuti->tanggal_berakhir ? Carbon::parse($cuti->tanggal_berakhir) : $startDate;

        return $this->createLedgerEntry($employee, [
            'entry_type' => LeaveBalanceLedger::TYPE_USAGE,
            'direction' => LeaveBalanceLedger::DIRECTION_DEBIT,
            'amount' => $cuti->jumlah,
            'period_year' => (int) $startDate->format('Y'),
            'transaction_date' => now()->toDateString(),
            'effective_date' => $startDate->toDateString(),
            'expires_at' => null,
            'reference_type' => 'cuti_izin',
            'reference_id' => (string) $cuti->id,
            'note' => 'Pemakaian cuti tahunan ' . $startDate->format('d M Y') . ' - ' . $endDate->format('d M Y'),
            'created_by' => (string) $actor->id,
        ], $actor);
    }

    private function createLedgerEntry(Employee $employee, array $data, User $actor): LeaveBalanceLedger
    {
        $amount = round((float) $data['amount'], 2);

        if ($amount <= 0) {
            throw new LeaveBalanceException('Jumlah saldo cuti harus lebih dari 0.');
        }

        $balanceBefore = $this->currentBalance($employee);
        $direction = $data['direction'];
        $balanceAfter = $direction === LeaveBalanceLedger::DIRECTION_DEBIT
            ? $balanceBefore - $amount
            : $balanceBefore + $amount;

        if ($balanceAfter < 0) {
            throw new LeaveBalanceException('Saldo cuti tidak cukup untuk transaksi ini.');
        }

        $ledger = LeaveBalanceLedger::create([
            'employee_nik' => $employee->nik,
            'period_year' => $data['period_year'] ?? null,
            'transaction_date' => $data['transaction_date'],
            'effective_date' => $data['effective_date'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'entry_type' => $data['entry_type'],
            'direction' => $direction,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'note' => $data['note'] ?? null,
            'created_by' => $data['created_by'] ?? (string) $actor->id,
        ]);

        $employee->forceFill([
            'sisa_cuti' => $balanceAfter,
        ])->save();

        $this->recordAudit($ledger, $employee, $actor, $balanceBefore, $balanceAfter);

        return $ledger;
    }

    private function resolveDirection(string $entryType, ?string $direction): string
    {
        if (in_array($entryType, [
            LeaveBalanceLedger::TYPE_ANNUAL_GRANT,
            LeaveBalanceLedger::TYPE_OPENING_BALANCE,
            LeaveBalanceLedger::TYPE_CARRY_OVER,
        ], true)) {
            return LeaveBalanceLedger::DIRECTION_CREDIT;
        }

        if (in_array($entryType, [LeaveBalanceLedger::TYPE_USAGE, LeaveBalanceLedger::TYPE_EXPIRED], true)) {
            return LeaveBalanceLedger::DIRECTION_DEBIT;
        }

        if ($entryType === LeaveBalanceLedger::TYPE_ADJUSTMENT && in_array($direction, [
            LeaveBalanceLedger::DIRECTION_CREDIT,
            LeaveBalanceLedger::DIRECTION_DEBIT,
        ], true)) {
            return $direction;
        }

        throw new LeaveBalanceException('Jenis transaksi saldo cuti tidak valid.');
    }

    private function recordAudit(
        LeaveBalanceLedger $ledger,
        Employee $employee,
        User $actor,
        float $balanceBefore,
        float $balanceAfter
    ): void {
        app(AuditTrailService::class)->record([
            'event' => $ledger->entry_type === LeaveBalanceLedger::TYPE_USAGE
                ? 'leave_balance.usage.recorded'
                : 'leave_balance.manual.' . $ledger->direction,
            'module' => 'leave_balance',
            'auditable_type' => Employee::class,
            'auditable_id' => $employee->nik,
            'reference_table' => 'leave_balance_ledgers',
            'reference_id' => (string) $ledger->id,
            'employee_nik' => $employee->nik,
            'actor' => $actor,
            'old_values' => [
                'sisa_cuti' => $balanceBefore,
            ],
            'new_values' => [
                'sisa_cuti' => $balanceAfter,
                'entry_type' => $ledger->entry_type,
                'direction' => $ledger->direction,
                'amount' => (float) $ledger->amount,
            ],
            'metadata' => [
                'period_year' => $ledger->period_year,
                'reference_type' => $ledger->reference_type,
                'reference_id' => $ledger->reference_id,
            ],
            'note' => $ledger->note,
        ]);
    }
}
