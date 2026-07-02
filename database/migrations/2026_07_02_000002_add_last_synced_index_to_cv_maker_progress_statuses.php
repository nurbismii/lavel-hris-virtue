<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLastSyncedIndexToCvMakerProgressStatuses extends Migration
{
    public function up()
    {
        Schema::table('cv_maker_progress_statuses', function (Blueprint $table) {
            $table->index('last_synced_at', 'cv_progress_statuses_last_synced_idx');
        });
    }

    public function down()
    {
        Schema::table('cv_maker_progress_statuses', function (Blueprint $table) {
            $table->dropIndex('cv_progress_statuses_last_synced_idx');
        });
    }
}
