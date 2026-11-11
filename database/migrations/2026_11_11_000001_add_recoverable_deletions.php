<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['employees', 'perusahaan', 'departemens', 'divisis'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->softDeletes();
                $table->string('deleted_by', 36)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['employees', 'perusahaan', 'departemens', 'divisis'] as $name) {
            // Keep archived rows archived when rolling back application code.
            if (\Illuminate\Support\Facades\DB::table($name)->whereNotNull('deleted_at')->exists()) {
                throw new \RuntimeException('Pulihkan data di Tempat Sampah sebelum rollback migrasi pemulihan.');
            }
        }
        foreach (['employees', 'perusahaan', 'departemens', 'divisis'] as $name) {
            Schema::table($name, function (Blueprint $table) { $table->dropSoftDeletes(); $table->dropColumn('deleted_by'); });
        }
    }
};
