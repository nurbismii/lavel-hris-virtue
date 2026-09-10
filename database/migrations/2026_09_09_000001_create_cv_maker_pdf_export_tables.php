<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cv_maker_pdf_batches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->uuid('idempotency_key')->unique();
            $table->string('requested_by', 36)->index();
            $table->string('mode', 16);
            $table->string('status', 24)->default('pending')->index();
            $table->json('filters');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedTinyInteger('packaging_attempts')->default(0);
            $table->string('result_path')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamp('files_purged_at')->nullable();
            $table->timestamps();
        });
        Schema::create('cv_maker_pdf_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('batch_id');
            $table->string('employee_nik', 32);
            $table->string('status', 24)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('file_path')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'employee_nik']);
            $table->index(['batch_id', 'status']);
        });
        Schema::create('cv_maker_pdf_downloads', function (Blueprint $table) {
            $table->string('employee_nik', 32)->primary();
            // Held until download, failure, cancellation or expiry, including ready files.
            $table->unsignedBigInteger('active_batch_id')->nullable()->index();
            $table->timestamp('downloaded_at')->nullable()->index();
            $table->string('downloaded_by', 36)->nullable();
            $table->unsignedBigInteger('last_batch_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_maker_pdf_downloads');
        Schema::dropIfExists('cv_maker_pdf_items');
        Schema::dropIfExists('cv_maker_pdf_batches');
    }
};
