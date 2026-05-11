<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_attendance_location_assignments')) {
            return;
        }

        Schema::create('employee_attendance_location_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('employee_nik', 32);
            $table->unsignedBigInteger('lokasi_absen_id');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('assigned_by', 36)->nullable();
            $table->string('batch_id', 36)->nullable();
            $table->string('assignment_source', 40)->default('bulk_filter');
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['employee_nik', 'effective_from'], 'eala_employee_start_unique');
            $table->index(['employee_nik', 'effective_from', 'effective_until'], 'eala_employee_period_idx');
            $table->index(['lokasi_absen_id', 'effective_from'], 'eala_location_start_idx');
            $table->index('batch_id', 'eala_batch_idx');
            $table->index(['assigned_by', 'created_at'], 'eala_assigned_by_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attendance_location_assignments');
    }
};
