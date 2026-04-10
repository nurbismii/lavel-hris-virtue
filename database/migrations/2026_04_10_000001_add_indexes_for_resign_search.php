<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIndexesForResignSearch extends Migration
{
    public function up()
    {
        $this->addIndexIfMissing('resign', 'idx_resign_nik_karyawan', '`nik_karyawan`', ['nik_karyawan']);
        $this->addIndexIfMissing('resign', 'idx_resign_tanggal_keluar', '`tanggal_keluar`', ['tanggal_keluar']);
        $this->addIndexIfMissing('resign', 'idx_resign_tipe', '`tipe`', ['tipe']);
        $this->addIndexIfMissing('employees', 'idx_employees_nama_karyawan', '`nama_karyawan`(100)', ['nama_karyawan']);
    }

    public function down()
    {
        $this->dropIndexIfExists('employees', 'idx_employees_nama_karyawan');
        $this->dropIndexIfExists('resign', 'idx_resign_tipe');
        $this->dropIndexIfExists('resign', 'idx_resign_tanggal_keluar');
        $this->dropIndexIfExists('resign', 'idx_resign_nik_karyawan');
    }

    private function addIndexIfMissing($table, $index, $columnsSql, array $requiredColumns)
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        foreach ($requiredColumns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return;
            }
        }

        if ($this->indexExists($table, $index) || $this->columnsAreIndexed($table, $requiredColumns)) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$columnsSql})");
    }

    private function dropIndexIfExists($table, $index)
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $index)) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
    }

    private function indexExists($table, $index)
    {
        return DB::table('information_schema.statistics')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    private function columnsAreIndexed($table, array $columns)
    {
        $indexes = DB::table('information_schema.statistics')
            ->select('index_name', 'seq_in_index', 'column_name')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', $table)
            ->orderBy('index_name')
            ->orderBy('seq_in_index')
            ->get()
            ->groupBy('index_name');

        foreach ($indexes as $indexedColumns) {
            $indexedColumns = $indexedColumns->pluck('column_name')->values()->all();

            if (array_slice($indexedColumns, 0, count($columns)) === array_values($columns)) {
                return true;
            }
        }

        return false;
    }
}
