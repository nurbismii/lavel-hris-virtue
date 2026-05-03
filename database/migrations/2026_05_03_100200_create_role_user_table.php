<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('role_user')) {
            return;
        }

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
            $table->index('user_id');
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
