<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('work_patterns')) {
            return;
        }

        Schema::table('work_patterns', function (Blueprint $table) {
            if (!Schema::hasColumn('work_patterns', 'pattern_basis')) {
                $table->string('pattern_basis', 20)->default('cycle')->after('name');
            }

            if (!Schema::hasColumn('work_patterns', 'weekly_work_days')) {
                $table->text('weekly_work_days')->nullable()->after('off_duration_unit');
            }
        });

        if (Schema::hasColumn('work_patterns', 'pattern_basis')) {
            DB::table('work_patterns')
                ->whereNull('pattern_basis')
                ->update(['pattern_basis' => 'cycle']);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('work_patterns')) {
            return;
        }

        Schema::table('work_patterns', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('work_patterns', 'weekly_work_days')) {
                $columns[] = 'weekly_work_days';
            }

            if (Schema::hasColumn('work_patterns', 'pattern_basis')) {
                $columns[] = 'pattern_basis';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
