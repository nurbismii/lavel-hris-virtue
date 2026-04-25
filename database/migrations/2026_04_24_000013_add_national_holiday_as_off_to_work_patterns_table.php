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

        if (!Schema::hasColumn('work_patterns', 'national_holiday_as_off')) {
            Schema::table('work_patterns', function (Blueprint $table) {
                $table->boolean('national_holiday_as_off')->default(true)->after('off_duration_unit');
            });
        }

        DB::table('work_patterns')
            ->where('work_duration_unit', 'week')
            ->where('off_duration_unit', 'week')
            ->where('off_duration_value', 2)
            ->whereIn('work_duration_value', [8, 10])
            ->update(['national_holiday_as_off' => false]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('work_patterns') || !Schema::hasColumn('work_patterns', 'national_holiday_as_off')) {
            return;
        }

        Schema::table('work_patterns', function (Blueprint $table) {
            $table->dropColumn('national_holiday_as_off');
        });
    }
};
