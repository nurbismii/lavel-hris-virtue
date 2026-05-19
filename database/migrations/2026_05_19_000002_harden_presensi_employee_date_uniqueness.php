<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'absensis_nik_tanggal_unique';

    public function up(): void
    {
        if (
            !Schema::hasTable('absensis')
            || !Schema::hasColumn('absensis', 'nik_karyawan')
            || !Schema::hasColumn('absensis', 'tanggal')
            || $this->indexExists(self::INDEX_NAME)
        ) {
            return;
        }

        if ($this->hasDuplicateAttendanceRows()) {
            throw new RuntimeException(
                'Tidak bisa menambahkan unique index absensis_nik_tanggal_unique karena masih ada duplikasi absensis untuk kombinasi nik_karyawan dan tanggal. Jalankan php artisan presensi:dedupe-employee-date untuk audit, lalu php artisan presensi:dedupe-employee-date --apply untuk membersihkan grup yang aman digabung.'
            );
        }

        Schema::table('absensis', function (Blueprint $table) {
            $table->unique(['nik_karyawan', 'tanggal'], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('absensis') || !$this->indexExists(self::INDEX_NAME)) {
            return;
        }

        Schema::table('absensis', function (Blueprint $table) {
            $table->dropUnique(self::INDEX_NAME);
        });
    }

    private function hasDuplicateAttendanceRows(): bool
    {
        return DB::table('absensis')
            ->select('nik_karyawan', 'tanggal')
            ->whereNotNull('nik_karyawan')
            ->whereNotNull('tanggal')
            ->groupBy('nik_karyawan', 'tanggal')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();
    }

    private function indexExists(string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            return collect(DB::select('SHOW INDEX FROM absensis'))->contains(function ($index) use ($indexName) {
                return (string) ($index->Key_name ?? '') === $indexName;
            });
        }

        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('absensis')"))->contains(function ($index) use ($indexName) {
                return (string) ($index->name ?? '') === $indexName;
            });
        }

        return false;
    }
};
