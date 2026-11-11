<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->string('type', 10);
            $table->boolean('fixed')->default(false);
            $table->unsignedInteger('revision')->default(1);
            $table->string('created_by', 36);
            $table->string('deleted_by', 36)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('salary_components')->exists()) {
            throw new \RuntimeException('Master komponen gaji berisi data. Arsipkan database sebelum rollback.');
        }
        Schema::dropIfExists('salary_components');
    }
};
