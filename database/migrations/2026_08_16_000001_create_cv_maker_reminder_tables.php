<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_maker_reminder_batches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('batch_uuid')->unique();
            $table->uuid('idempotency_key')->unique();
            $table->string('requested_by', 36);
            $table->string('selection_mode', 20);
            $table->string('status', 30)->default('queued');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('pending_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->json('filters')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['requested_by', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('cv_maker_reminder_deliveries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('batch_id');
            $table->string('employee_nik', 32);
            $table->string('user_id', 36)->nullable();
            $table->string('email', 255)->nullable();
            $table->unsignedTinyInteger('current_step')->nullable();
            $table->string('current_step_label', 80)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('skip_reason', 255)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('batch_id', 'cv_reminder_delivery_batch_fk')
                ->references('id')->on('cv_maker_reminder_batches')->onDelete('cascade');
            $table->unique(['batch_id', 'employee_nik'], 'cv_reminder_batch_employee_unique');
            $table->index(['employee_nik', 'status', 'sent_at'], 'cv_reminder_nik_status_sent_idx');
            $table->index(['batch_id', 'status'], 'cv_reminder_batch_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_maker_reminder_deliveries');
        Schema::dropIfExists('cv_maker_reminder_batches');
    }
};
