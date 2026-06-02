<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_movements')) {
            return;
        }

        if (
            Schema::hasColumn('employee_movements', 'application_attempted_at')
            && Schema::hasColumn('employee_movements', 'application_error')
        ) {
            return;
        }

        Schema::table('employee_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_movements', 'application_attempted_at')) {
                $table->timestamp('application_attempted_at')->nullable()->after('applied_at');
            }

            if (!Schema::hasColumn('employee_movements', 'application_error')) {
                $table->string('application_error', 500)->nullable()->after('application_attempted_at');
            }
        });
    }

    public function down(): void
    {
        // No-op: these columns are part of the base table for fresh installs.
    }
};
