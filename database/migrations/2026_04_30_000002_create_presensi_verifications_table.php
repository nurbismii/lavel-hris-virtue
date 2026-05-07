<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePresensiVerificationsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('absensis') || Schema::hasTable('presensi_verifications')) {
            return;
        }

        Schema::create('presensi_verifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('presensi_id');
            $table->string('nik_karyawan', 20);
            $table->date('tanggal');
            $table->string('attendance_type', 32);
            $table->string('status', 64);
            $table->boolean('face_verified')->default(false);
            $table->string('face_selfie_path')->nullable();
            $table->string('face_selfie_hash', 64)->nullable();
            $table->decimal('face_verification_distance', 8, 6)->nullable();
            $table->timestamp('face_verified_at')->nullable();
            $table->string('face_verification_method', 64)->nullable();
            $table->text('face_verification_meta')->nullable();
            $table->string('presensi_challenge_id', 80)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['presensi_id', 'attendance_type'], 'presensi_verifications_presensi_type_unique');
            $table->index(['nik_karyawan', 'tanggal', 'attendance_type'], 'presensi_verifications_nik_tanggal_type_index');
            $table->index(['status', 'created_at'], 'presensi_verifications_status_created_index');
            $table->index('presensi_challenge_id', 'presensi_verifications_challenge_index');
            $table->index('face_selfie_hash', 'presensi_verifications_selfie_hash_index');

            $table->foreign('presensi_id', 'presensi_verifications_presensi_fk')
                ->references('id')
                ->on('absensis')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('presensi_verifications');
    }
}
