<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('warning_letter_requests', 'verification_token')) {
            Schema::table('warning_letter_requests', function (Blueprint $table) {
                $table->text('verification_token')->nullable()->after('letter_number');
                $table->char('verification_token_hash', 64)->nullable()->unique()->after('verification_token');
                $table->char('verification_document_hash', 64)->nullable()->after('verification_token_hash');
                $table->timestamp('verification_revoked_at')->nullable()->index()->after('verification_document_hash');
                $table->string('verification_revoked_by', 100)->nullable()->after('verification_revoked_at');
            });
        }

        // A failed MySQL FK statement can leave this new table behind because DDL is auto-committed.
        Schema::dropIfExists('warning_letter_verification_logs');

        Schema::create('warning_letter_verification_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warning_letter_request_id');
            $table->string('event', 20)->default('scan');
            $table->string('actor_id', 100)->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->timestamp('accessed_at');
            $table->index(['warning_letter_request_id', 'accessed_at'], 'warning_letter_verification_access_idx');
            $table->foreign('warning_letter_request_id', 'warning_letter_verify_request_fk')
                ->references('id')->on('warning_letter_requests')->cascadeOnDelete();
        });

        DB::table('warning_letter_requests')
            ->where('status', 'approved')
            ->whereNotNull('letter_snapshot')
            ->orderBy('id')
            ->chunkById(200, function ($letters) {
                foreach ($letters as $letter) {
                    $token = bin2hex(random_bytes(32));
                    $snapshot = json_decode($letter->letter_snapshot, true) ?: [];
                    DB::table('warning_letter_requests')->where('id', $letter->id)->update([
                        'verification_token' => Crypt::encryptString($token),
                        'verification_token_hash' => hash('sha256', $token),
                        'verification_document_hash' => hash_hmac('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (string) config('app.key')),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('warning_letter_verification_logs');
        Schema::table('warning_letter_requests', function (Blueprint $table) {
            $table->dropUnique(['verification_token_hash']);
            $table->dropIndex(['verification_revoked_at']);
            $table->dropColumn([
                'verification_token',
                'verification_token_hash',
                'verification_document_hash',
                'verification_revoked_at',
                'verification_revoked_by',
            ]);
        });
    }
};
