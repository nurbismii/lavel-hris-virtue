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
            if (!Schema::hasColumn('work_patterns', 'sixth_day_start_time')) {
                $table->time('sixth_day_start_time')->nullable()->after('break_end_time');
            }

            if (!Schema::hasColumn('work_patterns', 'sixth_day_end_time')) {
                $table->time('sixth_day_end_time')->nullable()->after('sixth_day_start_time');
            }

            if (!Schema::hasColumn('work_patterns', 'sixth_day_break_start_time')) {
                $table->time('sixth_day_break_start_time')->nullable()->after('sixth_day_end_time');
            }

            if (!Schema::hasColumn('work_patterns', 'sixth_day_break_end_time')) {
                $table->time('sixth_day_break_end_time')->nullable()->after('sixth_day_break_start_time');
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

            if (Schema::hasColumn('work_patterns', 'sixth_day_break_end_time')) {
                $columns[] = 'sixth_day_break_end_time';
            }

            if (Schema::hasColumn('work_patterns', 'sixth_day_break_start_time')) {
                $columns[] = 'sixth_day_break_start_time';
            }

            if (Schema::hasColumn('work_patterns', 'sixth_day_end_time')) {
                $columns[] = 'sixth_day_end_time';
            }

            if (Schema::hasColumn('work_patterns', 'sixth_day_start_time')) {
                $columns[] = 'sixth_day_start_time';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
