<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('import_histories', function (Blueprint $table) {
            $table->string('file_checksum', 64)->nullable()->after('file_size');
            $table->string('failure_file_path', 500)->nullable()->after('file_path');
            $table->timestamp('expires_at')->nullable()->after('finished_at')->index();
            $table->timestamp('confirmed_at')->nullable()->after('expires_at');
            $table->string('confirmed_by', 36)->nullable()->after('confirmed_at');
        });

        Schema::table('cuti_roster', function (Blueprint $table) {
            $table->unsignedBigInteger('roster_schedule_id')->nullable()->index()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('cuti_roster', function (Blueprint $table) {
            $table->dropIndex(['roster_schedule_id']);
            $table->dropColumn('roster_schedule_id');
        });
        Schema::table('import_histories', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['file_checksum', 'failure_file_path', 'expires_at', 'confirmed_at', 'confirmed_by']);
        });
    }
};
