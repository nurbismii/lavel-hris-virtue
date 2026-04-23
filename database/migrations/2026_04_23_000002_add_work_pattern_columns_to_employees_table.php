<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'work_pattern_id')) {
                $table->unsignedBigInteger('work_pattern_id')->nullable()->after('jam_kerja');
            }

            if (!Schema::hasColumn('employees', 'work_pattern_start_date')) {
                $table->date('work_pattern_start_date')->nullable()->after('work_pattern_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('employees', 'work_pattern_start_date')) {
                $columns[] = 'work_pattern_start_date';
            }

            if (Schema::hasColumn('employees', 'work_pattern_id')) {
                $columns[] = 'work_pattern_id';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
