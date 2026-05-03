<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'authorized_departemen_ids')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->json('authorized_departemen_ids')->nullable()->after('authorized_divisi_ids');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'authorized_departemen_ids')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('authorized_departemen_ids');
        });
    }
};
