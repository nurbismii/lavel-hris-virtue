<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCvMakerProgressSnapshotTables extends Migration
{
    public function up()
    {
        Schema::create('cv_maker_progress_statuses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('employee_nik', 32)->unique();
            $table->unsignedBigInteger('cv_user_id')->nullable();
            $table->unsignedBigInteger('cv_profile_id')->nullable();
            $table->string('cv_status', 40)->nullable();
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->string('current_step_key', 40)->nullable();
            $table->string('current_step_label', 80)->nullable();
            $table->unsignedTinyInteger('completed_step_count')->default(0);
            $table->unsignedTinyInteger('total_step_count')->default(8);
            $table->boolean('is_complete')->default(false);
            $table->boolean('needs_reminder')->default(false);
            $table->string('reminder_reason', 500)->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('completed_steps')->nullable();
            $table->json('missing_steps')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('cv_profile_id');
            $table->index('current_step');
            $table->index('needs_reminder');
            $table->index('last_activity_at');
        });

        Schema::create('cv_maker_progress_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cv_maker_progress_status_id')->nullable();
            $table->string('employee_nik', 32);
            $table->string('event_type', 40);
            $table->unsignedTinyInteger('from_step')->nullable();
            $table->unsignedTinyInteger('to_step')->nullable();
            $table->boolean('from_needs_reminder')->nullable();
            $table->boolean('to_needs_reminder')->nullable();
            $table->string('cv_status', 40)->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->string('message', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('cv_maker_progress_status_id', 'cv_progress_histories_status_fk')
                ->references('id')
                ->on('cv_maker_progress_statuses')
                ->onDelete('cascade');

            $table->index(['employee_nik', 'created_at'], 'cv_progress_histories_nik_created_idx');
            $table->index('event_type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cv_maker_progress_histories');
        Schema::dropIfExists('cv_maker_progress_statuses');
    }
}
