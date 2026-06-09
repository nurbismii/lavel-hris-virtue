<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_contracts')) {
            return;
        }

        foreach (['status', 'signing_method', 'signature_status', 'contract_end_date'] as $column) {
            if (!Schema::hasColumn('employee_contracts', $column)) {
                return;
            }
        }

        Schema::table('employee_contracts', function (Blueprint $table) {
            if (!$this->indexExists('employee_contracts', 'employee_contracts_signature_reminder_idx')) {
                $table->index(
                    ['status', 'signing_method', 'signature_status', 'contract_end_date'],
                    'employee_contracts_signature_reminder_idx'
                );
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('employee_contracts')) {
            return;
        }

        Schema::table('employee_contracts', function (Blueprint $table) {
            if ($this->indexExists('employee_contracts', 'employee_contracts_signature_reminder_idx')) {
                $table->dropIndex('employee_contracts_signature_reminder_idx');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
