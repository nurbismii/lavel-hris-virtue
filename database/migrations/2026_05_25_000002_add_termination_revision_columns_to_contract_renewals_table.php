<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_contract_renewals')) {
            return;
        }

        Schema::table('employee_contract_renewals', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_contract_renewals', 'termination_revised_at')) {
                $table->timestamp('termination_revised_at')->nullable()->after('employee_status_sync_note');
            }

            if (!Schema::hasColumn('employee_contract_renewals', 'termination_revised_by_user_id')) {
                $table->string('termination_revised_by_user_id', 64)->nullable()->after('termination_revised_at');
            }

            if (!Schema::hasColumn('employee_contract_renewals', 'termination_revision_note')) {
                $table->text('termination_revision_note')->nullable()->after('termination_revised_by_user_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('employee_contract_renewals')) {
            return;
        }

        Schema::table('employee_contract_renewals', function (Blueprint $table) {
            if (Schema::hasColumn('employee_contract_renewals', 'termination_revision_note')) {
                $table->dropColumn('termination_revision_note');
            }

            if (Schema::hasColumn('employee_contract_renewals', 'termination_revised_by_user_id')) {
                $table->dropColumn('termination_revised_by_user_id');
            }

            if (Schema::hasColumn('employee_contract_renewals', 'termination_revised_at')) {
                $table->dropColumn('termination_revised_at');
            }
        });
    }
};
