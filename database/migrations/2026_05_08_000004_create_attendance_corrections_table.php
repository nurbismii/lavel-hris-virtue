<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_corrections')) {
            return;
        }

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nik_karyawan', 32);
            $table->unsignedBigInteger('presensi_id')->nullable();
            $table->date('tanggal');
            $table->dateTime('requested_jam_masuk')->nullable();
            $table->dateTime('requested_jam_istirahat')->nullable();
            $table->dateTime('requested_jam_kembali_istirahat')->nullable();
            $table->dateTime('requested_jam_pulang')->nullable();
            $table->boolean('change_status_presensi')->default(false);
            $table->string('requested_status_presensi', 100)->nullable();
            $table->longText('old_values')->nullable();
            $table->longText('applied_values')->nullable();
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->unsignedTinyInteger('status_hod')->default(0);
            $table->string('hod_processed_by', 36)->nullable();
            $table->timestamp('hod_processed_at')->nullable();
            $table->string('hod_rejection_reason', 500)->nullable();
            $table->unsignedTinyInteger('status_hrd')->default(0);
            $table->string('hrd_processed_by', 36)->nullable();
            $table->timestamp('hrd_processed_at')->nullable();
            $table->string('hrd_rejection_reason', 500)->nullable();
            $table->string('applied_by', 36)->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->string('created_by', 36)->nullable();
            $table->timestamps();

            $table->index(['nik_karyawan', 'tanggal']);
            $table->index(['status_hod', 'status_hrd', 'created_at'], 'attendance_corrections_status_queue_index');
            $table->index(['presensi_id']);
            $table->index(['created_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
    }
};
