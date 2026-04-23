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
            if (!Schema::hasColumn('work_patterns', 'break_start_time')) {
                $table->time('break_start_time')->nullable();
            }

            if (!Schema::hasColumn('work_patterns', 'break_end_time')) {
                $table->time('break_end_time')->nullable();
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

            if (Schema::hasColumn('work_patterns', 'break_end_time')) {
                $columns[] = 'break_end_time';
            }

            if (Schema::hasColumn('work_patterns', 'break_start_time')) {
                $columns[] = 'break_start_time';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
