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

        $needsUpgrade = collect([
            'hod_status',
            'hod_processed_by',
            'hod_processed_at',
            'hod_rejection_reason',
            'hrd_status',
            'hrd_processed_by',
            'hrd_processed_at',
            'hrd_rejection_reason',
        ])->contains(fn($column) => !Schema::hasColumn('employee_movements', $column));

        if (!$needsUpgrade) {
            return;
        }

        Schema::table('employee_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_movements', 'hod_status')) {
                $table->unsignedTinyInteger('hod_status')->default(0);
            }

            if (!Schema::hasColumn('employee_movements', 'hod_processed_by')) {
                $table->string('hod_processed_by', 36)->nullable();
            }

            if (!Schema::hasColumn('employee_movements', 'hod_processed_at')) {
                $table->timestamp('hod_processed_at')->nullable();
            }

            if (!Schema::hasColumn('employee_movements', 'hod_rejection_reason')) {
                $table->string('hod_rejection_reason', 500)->nullable();
            }

            if (!Schema::hasColumn('employee_movements', 'hrd_status')) {
                $table->unsignedTinyInteger('hrd_status')->default(0);
            }

            if (!Schema::hasColumn('employee_movements', 'hrd_processed_by')) {
                $table->string('hrd_processed_by', 36)->nullable();
            }

            if (!Schema::hasColumn('employee_movements', 'hrd_processed_at')) {
                $table->timestamp('hrd_processed_at')->nullable();
            }

            if (!Schema::hasColumn('employee_movements', 'hrd_rejection_reason')) {
                $table->string('hrd_rejection_reason', 500)->nullable();
            }
        });

        Schema::table('employee_movements', function (Blueprint $table) {
            $table->index(['hod_status', 'hrd_status', 'status'], 'employee_movements_approval_idx');
            $table->index('hod_processed_by', 'employee_movements_hod_processor_idx');
            $table->index('hrd_processed_by', 'employee_movements_hrd_processor_idx');
        });
    }

    public function down(): void
    {
        // No-op: these columns are part of the base table for fresh installs.
        // Dropping them here could remove columns created by the base migration.
    }
};
