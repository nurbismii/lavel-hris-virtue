<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_contract_renewals')) {
            return;
        }

        Schema::table('employee_contract_renewals', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_contract_renewals', 'employee_status_synced_at')) {
                $table->timestamp('employee_status_synced_at')->nullable()->after('employee_notified_at');
            }

            if (!Schema::hasColumn('employee_contract_renewals', 'employee_status_sync_note')) {
                $table->string('employee_status_sync_note', 500)->nullable()->after('employee_status_synced_at');
            }

            if (!$this->indexExists('employee_contract_renewals', 'contract_renewals_status_sync_idx')) {
                $table->index(
                    ['status', 'current_contract_end_date', 'employee_status_synced_at'],
                    'contract_renewals_status_sync_idx'
                );
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('employee_contract_renewals')) {
            return;
        }

        Schema::table('employee_contract_renewals', function (Blueprint $table) {
            if ($this->indexExists('employee_contract_renewals', 'contract_renewals_status_sync_idx')) {
                $table->dropIndex('contract_renewals_status_sync_idx');
            }

            if (Schema::hasColumn('employee_contract_renewals', 'employee_status_sync_note')) {
                $table->dropColumn('employee_status_sync_note');
            }

            if (Schema::hasColumn('employee_contract_renewals', 'employee_status_synced_at')) {
                $table->dropColumn('employee_status_synced_at');
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
