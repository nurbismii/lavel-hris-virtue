<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHrReviewColumnsToPresensiVerificationsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('presensi_verifications')) {
            return;
        }

        Schema::table('presensi_verifications', function (Blueprint $table) {
            if (!Schema::hasColumn('presensi_verifications', 'review_decision')) {
                $table->string('review_decision', 32)->nullable()->after('submitted_at');
            }

            if (!Schema::hasColumn('presensi_verifications', 'review_note')) {
                $table->text('review_note')->nullable()->after('review_decision');
            }

            if (!Schema::hasColumn('presensi_verifications', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('review_note');
            }

            if (!Schema::hasColumn('presensi_verifications', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
                $table->index(['review_decision', 'reviewed_at'], 'presensi_verifications_review_index');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('presensi_verifications')) {
            return;
        }

        Schema::table('presensi_verifications', function (Blueprint $table) {
            if (Schema::hasColumn('presensi_verifications', 'reviewed_at')) {
                $table->dropIndex('presensi_verifications_review_index');
            }

            $columns = [
                'review_decision',
                'review_note',
                'reviewed_by',
                'reviewed_at',
            ];

            $existingColumns = array_filter($columns, function ($column) {
                return Schema::hasColumn('presensi_verifications', $column);
            });

            if (!empty($existingColumns)) {
                $table->dropColumn($existingColumns);
            }
        });
    }
}
