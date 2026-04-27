<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToResignTable extends Migration
{
    public function up()
    {
        Schema::table('resign', function (Blueprint $table) {
            $table->index('nik_karyawan', 'resign_nik_karyawan_index');
            $table->index('tanggal_keluar', 'resign_tanggal_keluar_index');
            $table->index('tipe', 'resign_tipe_index');

            $table->index(
                ['tanggal_keluar', 'tipe'],
                'resign_tanggal_keluar_tipe_index'
            );

            $table->index(
                ['nik_karyawan', 'tanggal_keluar'],
                'resign_nik_tanggal_index'
            );
        });
    }

    public function down()
    {
        Schema::table('resign', function (Blueprint $table) {
            $table->dropIndex('resign_nik_karyawan_index');
            $table->dropIndex('resign_tanggal_keluar_index');
            $table->dropIndex('resign_tipe_index');
            $table->dropIndex('resign_tanggal_keluar_tipe_index');
            $table->dropIndex('resign_nik_tanggal_index');
        });
    }
}
