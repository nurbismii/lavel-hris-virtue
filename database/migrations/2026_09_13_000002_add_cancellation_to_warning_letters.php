<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('warning_letter_requests', function (Blueprint $table) {
            $table->string('cancelled_by', 100)->nullable()->after('verification_revoked_by');
            $table->string('cancelled_by_name', 180)->nullable()->after('cancelled_by');
            $table->timestamp('cancelled_at')->nullable()->index()->after('cancelled_by_name');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
        });

        Schema::table('sp_report', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('sp_report', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('warning_letter_requests', function (Blueprint $table) {
            $table->dropIndex(['cancelled_at']);
            $table->dropColumn(['cancelled_by', 'cancelled_by_name', 'cancelled_at', 'cancellation_reason']);
        });
    }
};
