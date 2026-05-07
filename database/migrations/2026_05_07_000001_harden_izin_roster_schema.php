<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ROSTER_NOMOR_SURAT_INDEX = 'cuti_roster_nomor_surat_unique';

    public function up(): void
    {
        if (Schema::hasTable('cuti_izin') && !Schema::hasColumn('cuti_izin', 'tipe_izin')) {
            Schema::table('cuti_izin', function (Blueprint $table) {
                $column = $table->string('tipe_izin', 225)->nullable();

                if (DB::getDriverName() === 'mysql') {
                    $column->after('tipe');
                }
            });
        }

        if (
            DB::getDriverName() === 'mysql'
            && Schema::hasTable('cuti_roster')
            && Schema::hasColumn('cuti_roster', 'nomor_surat')
            && !$this->indexExists('cuti_roster', self::ROSTER_NOMOR_SURAT_INDEX)
        ) {
            $hasDuplicates = DB::table('cuti_roster')
                ->select('nomor_surat')
                ->whereNotNull('nomor_surat')
                ->where('nomor_surat', '<>', '')
                ->groupBy('nomor_surat')
                ->havingRaw('COUNT(*) > 1')
                ->exists();

            if ($hasDuplicates) {
                Log::warning('Unique index cuti_roster.nomor_surat skipped because duplicate numbers exist.');
                return;
            }

            Schema::table('cuti_roster', function (Blueprint $table) {
                $table->unique('nomor_surat', self::ROSTER_NOMOR_SURAT_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (
            DB::getDriverName() === 'mysql'
            && Schema::hasTable('cuti_roster')
            && $this->indexExists('cuti_roster', self::ROSTER_NOMOR_SURAT_INDEX)
        ) {
            Schema::table('cuti_roster', function (Blueprint $table) {
                $table->dropUnique(self::ROSTER_NOMOR_SURAT_INDEX);
            });
        }

        // Do not drop cuti_izin.tipe_izin on rollback because existing installations
        // may already contain production leave category data in that column.
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return !empty(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]));
    }
};
