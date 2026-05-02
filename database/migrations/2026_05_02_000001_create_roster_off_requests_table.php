<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRosterOffRequestsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('roster_off_requests')) {
            return;
        }

        Schema::create('roster_off_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nik_karyawan', 100);
            $table->string('requested_by', 36)->nullable();
            $table->date('tanggal_off');
            $table->text('alasan')->nullable();
            $table->unsignedTinyInteger('status_hod')->default(0);
            $table->unsignedTinyInteger('status_hrd')->default(0);
            $table->string('hod_processed_by', 36)->nullable();
            $table->timestamp('hod_processed_at')->nullable();
            $table->string('hrd_processed_by', 36)->nullable();
            $table->timestamp('hrd_processed_at')->nullable();
            $table->timestamps();

            $table->index(['nik_karyawan', 'tanggal_off'], 'roster_off_requests_nik_date_index');
            $table->index(['status_hod', 'status_hrd', 'tanggal_off'], 'roster_off_requests_status_date_index');
            $table->index('requested_by', 'roster_off_requests_requested_by_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('roster_off_requests');
    }
}
