<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPresensiSecurityColumnsToAbsensisTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('absensis')) {
            return;
        }

        Schema::table('absensis', function (Blueprint $table) {
            if (!Schema::hasColumn('absensis', 'presensi_challenge_id')) {
                $column = $table->string('presensi_challenge_id', 80)->nullable();

                if (Schema::hasColumn('absensis', 'face_verification_meta')) {
                    $column->after('face_verification_meta');
                }

                $column->index('absensis_presensi_challenge_id_index');
            }

            if (!Schema::hasColumn('absensis', 'face_selfie_hash')) {
                $column = $table->string('face_selfie_hash', 64)->nullable();

                if (Schema::hasColumn('absensis', 'presensi_challenge_id')) {
                    $column->after('presensi_challenge_id');
                }

                $column->index('absensis_face_selfie_hash_index');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('absensis')) {
            return;
        }

        Schema::table('absensis', function (Blueprint $table) {
            if (Schema::hasColumn('absensis', 'presensi_challenge_id')) {
                $table->dropIndex('absensis_presensi_challenge_id_index');
                $table->dropColumn('presensi_challenge_id');
            }

            if (Schema::hasColumn('absensis', 'face_selfie_hash')) {
                $table->dropIndex('absensis_face_selfie_hash_index');
                $table->dropColumn('face_selfie_hash');
            }
        });
    }
}
