<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable('import_history_items')
            || Schema::hasColumn('import_history_items', 'employee_name')
        ) {
            return;
        }

        Schema::table('import_history_items', function (Blueprint $table) {
            $table->string('employee_name', 255)->nullable()->after('nik');
        });
    }

    public function down(): void
    {
        if (
            Schema::hasTable('import_history_items')
            && Schema::hasColumn('import_history_items', 'employee_name')
        ) {
            Schema::table('import_history_items', function (Blueprint $table) {
                $table->dropColumn('employee_name');
            });
        }
    }
};
