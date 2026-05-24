<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_contract_histories')) {
            Schema::create('employee_contract_histories', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('nik', 100);
                $table->string('employee_name', 180)->nullable();
                $table->string('marital_status', 80)->nullable();
                $table->string('employee_status', 120)->nullable();
                $table->string('contract_number', 150)->nullable();
                $table->date('entry_date')->nullable();
                $table->unsignedSmallInteger('history_sequence')->default(0);
                $table->string('history_type', 40);
                $table->string('raw_history_type', 80);
                $table->unsignedSmallInteger('duration_months')->nullable();
                $table->string('duration_label', 80)->nullable();
                $table->date('contract_end_date')->nullable();
                $table->timestamp('renewal_notice_sent_at')->nullable();
                $table->unsignedBigInteger('source_import_history_id')->nullable();
                $table->unsignedInteger('source_row_number')->nullable();
                $table->string('created_by', 64)->nullable();
                $table->timestamps();

                $table->index(['nik', 'contract_end_date'], 'contract_histories_nik_end_idx');
                $table->index(['nik', 'history_sequence'], 'contract_histories_nik_sequence_idx');
                $table->index(['contract_end_date', 'history_type'], 'contract_histories_end_type_idx');
                $table->index('renewal_notice_sent_at', 'contract_histories_notice_idx');
                $table->index('source_import_history_id', 'contract_histories_import_idx');
                $table->unique(
                    ['nik', 'history_sequence', 'contract_number', 'contract_end_date'],
                    'contract_histories_unique_row'
                );
            });
        }

        if (!Schema::hasTable('employee_contract_renewals')) {
            Schema::create('employee_contract_renewals', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('employee_nik', 100);
                $table->unsignedBigInteger('current_contract_history_id')->nullable();
                $table->unsignedBigInteger('current_contract_id')->nullable();
                $table->string('current_contract_number', 150)->nullable();
                $table->date('current_contract_end_date');
                $table->string('status', 40)->default('pending_delegation');
                $table->string('delegate_user_id', 64)->nullable();
                $table->string('delegated_by_user_id', 64)->nullable();
                $table->timestamp('delegated_at')->nullable();
                $table->unsignedTinyInteger('assessment_months')->nullable();
                $table->text('assessment_note')->nullable();
                $table->string('assessed_by_user_id', 64)->nullable();
                $table->timestamp('assessed_at')->nullable();
                $table->unsignedTinyInteger('hod_status')->default(0);
                $table->string('hod_approved_by_user_id', 64)->nullable();
                $table->timestamp('hod_approved_at')->nullable();
                $table->string('hod_rejection_reason', 500)->nullable();
                $table->unsignedTinyInteger('hrd_status')->default(0);
                $table->string('hrd_approved_by_user_id', 64)->nullable();
                $table->timestamp('hrd_approved_at')->nullable();
                $table->string('hrd_rejection_reason', 500)->nullable();
                $table->unsignedBigInteger('generated_contract_id')->nullable();
                $table->timestamp('employee_notified_at')->nullable();
                $table->string('created_by', 64)->nullable();
                $table->string('updated_by', 64)->nullable();
                $table->timestamps();

                $table->foreign('current_contract_history_id', 'contract_renewals_history_fk')
                    ->references('id')
                    ->on('employee_contract_histories')
                    ->onDelete('set null');
                $table->foreign('generated_contract_id', 'contract_renewals_generated_contract_fk')
                    ->references('id')
                    ->on('employee_contracts')
                    ->onDelete('set null');
                $table->index(['employee_nik', 'current_contract_end_date'], 'contract_renewals_employee_end_idx');
                $table->index(['status', 'current_contract_end_date'], 'contract_renewals_status_end_idx');
                $table->index(['delegate_user_id', 'status'], 'contract_renewals_delegate_status_idx');
                $table->index(['hod_status', 'hrd_status'], 'contract_renewals_approval_status_idx');
                $table->unique(['employee_nik', 'current_contract_end_date'], 'contract_renewals_unique_employee_end');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contract_renewals');
        Schema::dropIfExists('employee_contract_histories');
    }
};
