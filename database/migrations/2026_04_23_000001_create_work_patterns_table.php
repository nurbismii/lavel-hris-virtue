<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_patterns')) {
            return;
        }

        Schema::create('work_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 120);
            $table->unsignedInteger('work_duration_value');
            $table->string('work_duration_unit', 20);
            $table->unsignedInteger('off_duration_value');
            $table->string('off_duration_unit', 20);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_patterns');
    }
};
