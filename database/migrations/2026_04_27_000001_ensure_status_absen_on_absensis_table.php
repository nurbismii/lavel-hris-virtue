<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EnsureStatusAbsenOnAbsensisTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('absensis')) {
            return;
        }

        Schema::table('absensis', function (Blueprint $table) {
            if (!Schema::hasColumn('absensis', 'status_absen')) {
                $column = $table->string('status_absen', 64)->nullable();

                if (Schema::hasColumn('absensis', 'status_presensi')) {
                    $column->after('status_presensi');
                }
            }
        });
    }

    public function down()
    {
        // Kolom ini sudah ada di beberapa instalasi produksi; rollback sengaja no-op
        // agar tidak menghapus data status verifikasi presensi.
    }
}
