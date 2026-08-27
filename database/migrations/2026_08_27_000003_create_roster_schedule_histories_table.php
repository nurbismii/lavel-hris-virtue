<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roster_schedule_histories')) {
            return;
        }

        Schema::create('roster_schedule_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('roster_schedule_id')->nullable();
            $table->string('employee_nik', 50);
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_number');
            $table->date('scheduled_off_start');
            $table->date('scheduled_off_end');
            $table->string('classification', 30)->default('planned');
            $table->string('review_status', 20)->default('not_required');
            $table->text('remark_segment')->nullable();
            $table->text('raw_remark')->nullable();
            $table->string('source_file', 255);
            $table->string('source_sheet', 100)->nullable();
            $table->unsignedInteger('source_row')->nullable();
            $table->string('source_column', 10)->nullable();
            $table->string('source_remark_column', 10)->nullable();
            $table->timestamp('imported_at');
            $table->string('imported_by', 50)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reviewed_by', 50)->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();

            $table->unique(
                ['employee_nik', 'period_year', 'period_number', 'scheduled_off_start', 'source_file'],
                'roster_history_source_unique'
            );
            $table->index(['employee_nik', 'scheduled_off_start'], 'roster_history_employee_date_index');
            $table->index(['period_year', 'period_number'], 'roster_history_period_index');
            $table->index(['classification', 'review_status'], 'roster_history_review_index');
            $table->index('roster_schedule_id', 'roster_history_schedule_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_schedule_histories');
    }
};
