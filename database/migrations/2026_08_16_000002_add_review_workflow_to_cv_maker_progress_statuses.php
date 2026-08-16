<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cv_maker_progress_statuses', function (Blueprint $table) {
            $table->string('review_status', 40)->default('unreviewed')->after('needs_reminder');
            $table->string('reviewed_by', 36)->nullable()->after('review_status');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('review_note', 500)->nullable()->after('reviewed_at');
            $table->index(['review_status', 'current_step'], 'cv_progress_review_step_idx');
            $table->index(['reviewed_by', 'reviewed_at'], 'cv_progress_reviewer_time_idx');
        });
    }

    public function down(): void
    {
        Schema::table('cv_maker_progress_statuses', function (Blueprint $table) {
            $table->dropIndex('cv_progress_review_step_idx');
            $table->dropIndex('cv_progress_reviewer_time_idx');
            $table->dropColumn(['review_status', 'reviewed_by', 'reviewed_at', 'review_note']);
        });
    }
};
