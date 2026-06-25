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
            Schema::create('employee_attendance_settings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('employee_id', 100);
                $table->date('tanggal');
                $table->string('status', 20)->nullable();
                $table->string('periode', 7)->nullable();
                $table->timestamps();

                $table->unique(['employee_id', 'tanggal'], 'employee_attendance_settings_employee_date_unique');
                $table->index(['tanggal', 'status'], 'employee_attendance_settings_date_status_index');
                $table->index(['periode', 'employee_id'], 'employee_attendance_settings_period_employee_index');
            });

            return;
        }

        Schema::table('employee_attendance_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_attendance_settings', 'status')) {
                $table->string('status', 20)->nullable();
            }

            if (!Schema::hasColumn('employee_attendance_settings', 'periode')) {
                $table->string('periode', 7)->nullable();
            }
        });

        $this->backfillExistingRows();
        $this->addIndexIfMissing('employee_attendance_settings_employee_date_index', ['employee_id', 'tanggal']);
        $this->addIndexIfMissing('employee_attendance_settings_date_status_index', ['tanggal', 'status']);
        $this->addIndexIfMissing('employee_attendance_settings_period_employee_index', ['periode', 'employee_id']);
    }

    public function down(): void
    {
        // Intentionally no-op: this table can contain production attendance overrides.
    }

    private function backfillExistingRows(): void
    {
        DB::table('employee_attendance_settings')
            ->whereNull('status')
            ->update(['status' => 'OFF']);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("UPDATE employee_attendance_settings SET periode = DATE_FORMAT(tanggal, '%Y-%m') WHERE periode IS NULL AND tanggal IS NOT NULL");
            return;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement("UPDATE employee_attendance_settings SET periode = strftime('%Y-%m', tanggal) WHERE periode IS NULL AND tanggal IS NOT NULL");
        }
    }

    private function addIndexIfMissing(string $indexName, array $columns): void
    {
        if ($this->indexExists($indexName)) {
            return;
        }

        Schema::table('employee_attendance_settings', function (Blueprint $table) use ($indexName, $columns) {
            $table->index($columns, $indexName);
        });
    }

    private function indexExists(string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            return collect(DB::select('SHOW INDEX FROM employee_attendance_settings'))->contains(function ($index) use ($indexName) {
                return (string) ($index->Key_name ?? '') === $indexName;
            });
        }

        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('employee_attendance_settings')"))->contains(function ($index) use ($indexName) {
                return (string) ($index->name ?? '') === $indexName;
            });
        }

        return false;
    }
};
