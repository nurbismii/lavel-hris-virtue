<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_movements')) {
            return;
        }

        Schema::create('employee_movements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('employee_nik', 32);
            $table->string('movement_type', 20);
            $table->date('effective_date');
            $table->string('status', 20)->default('pending_hod');

            $table->string('old_posisi', 255)->nullable();
            $table->string('new_posisi', 255)->nullable();
            $table->string('old_jabatan', 255)->nullable();
            $table->string('new_jabatan', 255)->nullable();
            $table->unsignedBigInteger('old_departemen_id')->nullable();
            $table->unsignedBigInteger('new_departemen_id')->nullable();
            $table->unsignedBigInteger('old_divisi_id')->nullable();
            $table->unsignedBigInteger('new_divisi_id')->nullable();

            $table->string('reference_number', 120)->nullable();
            $table->string('reason', 1000);
            $table->string('created_by_user_id', 36)->nullable();
            $table->unsignedTinyInteger('hod_status')->default(0);
            $table->string('hod_processed_by', 36)->nullable();
            $table->timestamp('hod_processed_at')->nullable();
            $table->string('hod_rejection_reason', 500)->nullable();
            $table->unsignedTinyInteger('hrd_status')->default(0);
            $table->string('hrd_processed_by', 36)->nullable();
            $table->timestamp('hrd_processed_at')->nullable();
            $table->string('hrd_rejection_reason', 500)->nullable();
            $table->string('applied_by_user_id', 36)->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['employee_nik', 'effective_date'], 'employee_movements_employee_date_idx');
            $table->index(['movement_type', 'effective_date'], 'employee_movements_type_date_idx');
            $table->index(['status', 'effective_date'], 'employee_movements_status_date_idx');
            $table->index(['hod_status', 'hrd_status', 'status'], 'employee_movements_approval_idx');
            $table->index(['new_departemen_id', 'new_divisi_id'], 'employee_movements_new_org_idx');
            $table->index('created_by_user_id', 'employee_movements_creator_idx');
            $table->index('hod_processed_by', 'employee_movements_hod_processor_idx');
            $table->index('hrd_processed_by', 'employee_movements_hrd_processor_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_movements');
    }
};
