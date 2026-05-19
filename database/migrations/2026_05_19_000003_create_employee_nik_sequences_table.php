<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_nik_sequences')) {
            return;
        }

        Schema::create('employee_nik_sequences', function (Blueprint $table) {
            $table->string('prefix', 12)->primary();
            $table->unsignedInteger('last_suffix')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_nik_sequences');
    }
};
