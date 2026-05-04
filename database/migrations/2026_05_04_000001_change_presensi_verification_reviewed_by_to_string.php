<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChangePresensiVerificationReviewedByToString extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('presensi_verifications') || !Schema::hasColumn('presensi_verifications', 'reviewed_by')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `presensi_verifications` MODIFY `reviewed_by` VARCHAR(36) NULL');
    }

    public function down()
    {
        if (!Schema::hasTable('presensi_verifications') || !Schema::hasColumn('presensi_verifications', 'reviewed_by')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE `presensi_verifications` SET `reviewed_by` = NULL WHERE `reviewed_by` IS NOT NULL AND `reviewed_by` NOT REGEXP '^[0-9]+$'");
        DB::statement('ALTER TABLE `presensi_verifications` MODIFY `reviewed_by` BIGINT UNSIGNED NULL');
    }
}
