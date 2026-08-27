<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roster_schedules')) {
            return;
        }

        Schema::create('roster_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('employee_nik', 50);
            $table->unsignedInteger('cycle_number')->nullable();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_number');
            $table->date('work_start');
            $table->date('work_end');
            $table->date('off_start');
            $table->date('off_end');
            $table->unsignedTinyInteger('earned_off_days')->default(5);
            $table->string('realization_type', 30)->default('pending');
            $table->string('source', 20)->default('generated');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('reminder_queued_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('reminder_failed_at')->nullable();
            $table->string('reminder_email')->nullable();
            $table->string('reminder_error', 500)->nullable();
            $table->string('created_by', 50)->nullable();
            $table->string('updated_by', 50)->nullable();
            $table->timestamps();

            $table->unique(['employee_nik', 'off_start'], 'roster_schedules_employee_off_unique');
            $table->index(['period_year', 'period_number'], 'roster_schedules_period_index');
            $table->index(['is_active', 'off_start', 'reminder_sent_at'], 'roster_schedules_reminder_index');
            $table->index(['employee_nik', 'is_active', 'off_start'], 'roster_schedules_employee_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_schedules');
    }
};
