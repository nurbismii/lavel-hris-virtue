<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('absensis', 'absensis_tanggal_nik_index', ['tanggal', 'nik_karyawan']);
        $this->addIndex('absensis', 'absensis_tanggal_status_absen_index', ['tanggal', 'status_absen']);
        $this->addIndex('absensis', 'absensis_tanggal_status_presensi_index', ['tanggal', 'status_presensi']);
        $this->addIndex('log_presensi', 'log_presensi_nik_tanggal_created_index', ['nik_karyawan', 'tanggal', 'created_at']);
        $this->addIndex('employees', 'employees_attendance_scope_index', ['status_resign', 'area_kerja', 'departemen_id', 'divisi_id']);
    }

    public function down(): void
    {
        $this->dropIndex('employees', 'employees_attendance_scope_index');
        $this->dropIndex('log_presensi', 'log_presensi_nik_tanggal_created_index');
        $this->dropIndex('absensis', 'absensis_tanggal_status_presensi_index');
        $this->dropIndex('absensis', 'absensis_tanggal_status_absen_index');
        $this->dropIndex('absensis', 'absensis_tanggal_nik_index');
    }

    private function addIndex(string $table, string $index, array $columns): void
    {
        if (!Schema::hasTable($table) || $this->indexExists($table, $index)) {
            return;
        }

        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $index) {
            $table->index($columns, $index);
        });
    }

    private function dropIndex(string $table, string $index): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($index) {
            $table->dropIndex($index);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            return collect(DB::select('SHOW INDEX FROM `' . $table . '`'))->contains(function ($row) use ($index) {
                return (string) ($row->Key_name ?? '') === $index;
            });
        }

        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$table}')"))->contains(function ($row) use ($index) {
                return (string) ($row->name ?? '') === $index;
            });
        }

        return false;
    }
};
