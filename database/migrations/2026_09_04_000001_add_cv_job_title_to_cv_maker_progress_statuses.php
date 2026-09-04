<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCvJobTitleToCvMakerProgressStatuses extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('cv_maker_progress_statuses', 'cv_job_title')) {
            Schema::table('cv_maker_progress_statuses', function (Blueprint $table) {
                $table->string('cv_job_title', 255)->nullable()->after('cv_status');
                $table->index('cv_job_title', 'cv_progress_job_title_idx');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('cv_maker_progress_statuses', 'cv_job_title')) {
            Schema::table('cv_maker_progress_statuses', function (Blueprint $table) {
                $table->dropIndex('cv_progress_job_title_idx');
                $table->dropColumn('cv_job_title');
            });
        }
    }
}
