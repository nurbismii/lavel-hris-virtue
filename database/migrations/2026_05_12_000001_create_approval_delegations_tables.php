<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApprovalDelegationsTables extends Migration
{
    private array $approvalTables = [
        'cuti_izin',
        'cuti_roster',
        'roster_off_requests',
        'attendance_corrections',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('approval_delegations')) {
            Schema::create('approval_delegations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('hod_user_id', 36);
                $table->string('delegate_user_id', 36);
                $table->unsignedBigInteger('departemen_id')->nullable();
                $table->unsignedBigInteger('divisi_id')->nullable();
                $table->string('module', 50)->default('all');
                $table->boolean('is_active')->default(true);
                $table->string('created_by', 36)->nullable();
                $table->string('updated_by', 36)->nullable();
                $table->timestamps();

                $table->index(['hod_user_id', 'is_active']);
                $table->index(['delegate_user_id', 'is_active']);
                $table->index(['departemen_id', 'divisi_id']);
                $table->index(['module', 'is_active']);
                $table->unique(
                    ['hod_user_id', 'delegate_user_id', 'departemen_id', 'divisi_id', 'module'],
                    'approval_delegations_unique_scope'
                );
            });
        }

        if (!Schema::hasTable('approval_delegation_request_assignments')) {
            Schema::create('approval_delegation_request_assignments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('approval_delegation_id')->nullable();
                $table->string('approvable_type', 120);
                $table->string('approvable_id', 64);
                $table->string('delegate_user_id', 36);
                $table->string('assigned_by_hod_user_id', 36)->nullable();
                $table->string('module', 50);
                $table->unsignedTinyInteger('status')->default(0);
                $table->string('processed_by', 36)->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['delegate_user_id', 'module', 'status'], 'approval_delegate_assignment_user_status_idx');
                $table->index(['approvable_type', 'approvable_id'], 'approval_delegate_assignment_approvable_idx');
                $table->index('approval_delegation_id', 'approval_delegate_assignment_delegation_idx');
                $table->unique(
                    ['approvable_type', 'approvable_id', 'delegate_user_id', 'module'],
                    'approval_delegate_assignment_unique_user'
                );
            });
        }

        foreach ($this->approvalTables as $table) {
            $this->addDelegateColumns($table);
        }
    }

    public function down(): void
    {
        foreach ($this->approvalTables as $table) {
            $this->dropDelegateColumns($table);
        }

        Schema::dropIfExists('approval_delegation_request_assignments');
        Schema::dropIfExists('approval_delegations');
    }

    private function addDelegateColumns(string $table): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $tableBlueprint) use ($table) {
            if (!Schema::hasColumn($table, 'delegate_status')) {
                $tableBlueprint->unsignedTinyInteger('delegate_status')->nullable()->index();
            }

            if (!Schema::hasColumn($table, 'delegate_processed_by')) {
                $tableBlueprint->string('delegate_processed_by', 36)->nullable()->index();
            }

            if (!Schema::hasColumn($table, 'delegate_processed_at')) {
                $tableBlueprint->timestamp('delegate_processed_at')->nullable();
            }

            if (!Schema::hasColumn($table, 'delegate_rejection_reason')) {
                $tableBlueprint->string('delegate_rejection_reason', 500)->nullable();
            }
        });
    }

    private function dropDelegateColumns(string $table): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        $columns = [];

        foreach ([
            'delegate_status',
            'delegate_processed_by',
            'delegate_processed_at',
            'delegate_rejection_reason',
        ] as $column) {
            if (Schema::hasColumn($table, $column)) {
                $columns[] = $column;
            }
        }

        if (empty($columns)) {
            return;
        }

        Schema::table($table, function (Blueprint $tableBlueprint) use ($columns) {
            $tableBlueprint->dropColumn($columns);
        });
    }
}
