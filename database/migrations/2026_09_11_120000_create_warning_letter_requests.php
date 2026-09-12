<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('warning_letter_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('submission_token')->unique();
            $table->string('nik', 32)->index();
            $table->string('status', 20)->default('pending');
            $table->string('level_sp', 4);
            $table->text('keterangan');
            $table->string('pelapor', 128);
            $table->string('hod_name', 180);
            $table->date('tgl_mulai');
            $table->date('tgl_berakhir');
            $table->json('employee_snapshot');
            $table->json('letter_snapshot')->nullable();
            $table->string('created_by', 100);
            $table->string('created_by_name', 180);
            $table->string('reviewed_by', 100)->nullable();
            $table->string('reviewed_by_name', 180)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            // No FK: the legacy sp_report uses a signed integer primary key.
            $table->integer('sp_report_id')->nullable()->unique();
            $table->unsignedInteger('number_sequence')->nullable()->unique();
            $table->string('letter_number', 100)->nullable()->unique();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['created_by', 'created_at']);
        });
        Schema::create('warning_letter_sequences', function (Blueprint $table) {
            $table->string('key', 40)->primary();
            $table->unsignedInteger('last_number')->default(0);
        });
        DB::table('warning_letter_sequences')->insert(['key' => 'sp', 'last_number' => 0]);
    }

    public function down(): void
    {
        Schema::dropIfExists('warning_letter_requests');
        Schema::dropIfExists('warning_letter_sequences');
    }
};
