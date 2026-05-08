<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_balance_ledgers')) {
            return;
        }

        Schema::create('leave_balance_ledgers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('employee_nik', 32);
            $table->unsignedSmallInteger('period_year')->nullable();
            $table->date('transaction_date');
            $table->date('effective_date')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('entry_type', 40);
            $table->string('direction', 10);
            $table->decimal('amount', 8, 2);
            $table->decimal('balance_before', 8, 2);
            $table->decimal('balance_after', 8, 2);
            $table->string('reference_type', 80)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->string('note', 500)->nullable();
            $table->string('created_by', 36)->nullable();
            $table->timestamps();

            $table->index(['employee_nik', 'created_at']);
            $table->index(['employee_nik', 'period_year', 'created_at']);
            $table->index(['entry_type', 'transaction_date']);
            $table->index(['expires_at']);
            $table->index(['created_by', 'created_at']);
            $table->unique(
                ['employee_nik', 'entry_type', 'reference_type', 'reference_id'],
                'leave_balance_unique_reference'
            );
        });

        $this->backfillOpeningBalances();
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balance_ledgers');
    }

    private function backfillOpeningBalances(): void
    {
        if (!Schema::hasTable('employees') || !Schema::hasColumn('employees', 'sisa_cuti')) {
            return;
        }

        DB::table('employees')
            ->select(['nik', 'sisa_cuti'])
            ->whereNotNull('sisa_cuti')
            ->where('sisa_cuti', '>', 0)
            ->orderBy('nik')
            ->chunk(500, function ($employees) {
                $now = now();
                $rows = [];

                foreach ($employees as $employee) {
                    $balance = (float) $employee->sisa_cuti;

                    $rows[] = [
                        'employee_nik' => $employee->nik,
                        'period_year' => (int) $now->format('Y'),
                        'transaction_date' => $now->toDateString(),
                        'effective_date' => $now->toDateString(),
                        'expires_at' => null,
                        'entry_type' => 'opening_balance',
                        'direction' => 'credit',
                        'amount' => $balance,
                        'balance_before' => 0,
                        'balance_after' => $balance,
                        'reference_type' => 'employees.sisa_cuti',
                        'reference_id' => (string) $employee->nik,
                        'note' => 'Saldo awal dari data sisa_cuti sebelum ledger saldo cuti aktif.',
                        'created_by' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (!empty($rows)) {
                    DB::table('leave_balance_ledgers')->insert($rows);
                }
            });
    }
};
