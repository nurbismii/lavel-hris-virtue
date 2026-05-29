<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_period_locks')) {
            return;
        }

        Schema::create('attendance_period_locks', function (Blueprint $table) {
            $table->id();
            $table->string('period_key', 7)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('locked');
            $table->string('closed_by', 36)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('close_note')->nullable();
            $table->string('reopened_by', 36)->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_note')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->index(['status', 'start_date', 'end_date'], 'attendance_period_locks_status_range_index');
            $table->index(['start_date', 'end_date'], 'attendance_period_locks_range_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_period_locks');
    }
};
