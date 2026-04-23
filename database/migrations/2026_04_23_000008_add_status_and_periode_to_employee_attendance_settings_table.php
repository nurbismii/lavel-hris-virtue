<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_attendance_settings')) {
            return;
        }

        Schema::table('employee_attendance_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_attendance_settings', 'status')) {
                $table->string('status', 20)->nullable()->after('tanggal');
            }

            if (!Schema::hasColumn('employee_attendance_settings', 'periode')) {
                $table->string('periode', 7)->nullable()->after('status');
            }
        });

        if (Schema::hasColumn('employee_attendance_settings', 'status')) {
            DB::table('employee_attendance_settings')
                ->whereNull('status')
                ->update([
                    'status' => 'OFF',
                ]);
        }

        if (Schema::hasColumn('employee_attendance_settings', 'periode')) {
            DB::statement("UPDATE employee_attendance_settings SET periode = DATE_FORMAT(tanggal, '%Y-%m') WHERE periode IS NULL AND tanggal IS NOT NULL");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('employee_attendance_settings')) {
            return;
        }

        Schema::table('employee_attendance_settings', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('employee_attendance_settings', 'periode')) {
                $columns[] = 'periode';
            }

            if (Schema::hasColumn('employee_attendance_settings', 'status')) {
                $columns[] = 'status';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
