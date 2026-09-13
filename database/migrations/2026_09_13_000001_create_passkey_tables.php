<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_passkeys', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 32)->index();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('name', 80);
            $table->string('credential_hash', 64)->unique();
            $table->text('credential_id');
            $table->text('public_key');
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->boolean('backup_eligible')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('passkey_challenges', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('session_hash', 64)->index();
            $table->string('purpose', 16);
            $table->text('payload');
            $table->timestamp('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkey_challenges');
        Schema::dropIfExists('user_passkeys');
    }
};
