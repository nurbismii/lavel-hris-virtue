<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('import_histories')) {
            return;
        }

        Schema::create('import_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('import_id', 36)->nullable()->unique();
            $table->string('import_type', 80);
            $table->string('module', 80)->nullable();
            $table->string('source', 40)->default('excel');
            $table->string('status', 40)->default('queued');
            $table->string('file_name', 255)->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('disk', 80)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('inserted_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->longText('summary')->nullable();
            $table->longText('failure_samples')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->string('created_by', 36)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['import_type', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['created_by', 'created_at']);
            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_histories');
    }
};
