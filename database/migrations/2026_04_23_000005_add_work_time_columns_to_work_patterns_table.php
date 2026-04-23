<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('work_patterns')) {
            return;
        }

        Schema::table('work_patterns', function (Blueprint $table) {
            if (!Schema::hasColumn('work_patterns', 'start_time')) {
                $table->time('start_time')->nullable()->after('off_duration_unit');
            }

            if (!Schema::hasColumn('work_patterns', 'end_time')) {
                $table->time('end_time')->nullable()->after('start_time');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('work_patterns')) {
            return;
        }

        Schema::table('work_patterns', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('work_patterns', 'end_time')) {
                $columns[] = 'end_time';
            }

            if (Schema::hasColumn('work_patterns', 'start_time')) {
                $columns[] = 'start_time';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
