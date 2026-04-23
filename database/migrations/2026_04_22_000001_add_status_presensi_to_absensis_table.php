<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusPresensiToAbsensisTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('absensis')) {
            return;
        }

        Schema::table('absensis', function (Blueprint $table) {
            if (!Schema::hasColumn('absensis', 'status_presensi')) {
                $table->string('status_presensi', 100)->nullable()->after('jam_pulang');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('absensis') || !Schema::hasColumn('absensis', 'status_presensi')) {
            return;
        }

        Schema::table('absensis', function (Blueprint $table) {
            $table->dropColumn('status_presensi');
        });
    }
}
