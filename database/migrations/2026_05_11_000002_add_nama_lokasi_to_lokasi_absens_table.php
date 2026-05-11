<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lokasi_absens')) {
            return;
        }

        if (!Schema::hasColumn('lokasi_absens', 'nama_lokasi')) {
            Schema::table('lokasi_absens', function (Blueprint $table) {
                $table->string('nama_lokasi', 150)->nullable()->after('id');
                $table->index('nama_lokasi', 'lokasi_absens_nama_lokasi_idx');
            });
        }

        $this->makeDivisiNullable();
        $this->backfillLocationNames();
    }

    public function down(): void
    {
        if (!Schema::hasTable('lokasi_absens') || !Schema::hasColumn('lokasi_absens', 'nama_lokasi')) {
            return;
        }

        Schema::table('lokasi_absens', function (Blueprint $table) {
            $table->dropIndex('lokasi_absens_nama_lokasi_idx');
            $table->dropColumn('nama_lokasi');
        });
    }

    private function makeDivisiNullable(): void
    {
        if (!Schema::hasColumn('lokasi_absens', 'divisi_id')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $column = DB::selectOne("
                SELECT COLUMN_TYPE, IS_NULLABLE
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'lokasi_absens'
                    AND COLUMN_NAME = 'divisi_id'
            ");

            if ($column && strtoupper((string) $column->IS_NULLABLE) !== 'YES') {
                DB::statement('ALTER TABLE lokasi_absens MODIFY divisi_id ' . $column->COLUMN_TYPE . ' NULL');
            }
        }
    }

    private function backfillLocationNames(): void
    {
        if (!Schema::hasTable('divisis')) {
            DB::table('lokasi_absens')
                ->whereNull('nama_lokasi')
                ->orderBy('id')
                ->chunkById(500, function ($locations) {
                    foreach ($locations as $location) {
                        DB::table('lokasi_absens')
                            ->where('id', $location->id)
                            ->update(['nama_lokasi' => 'Lokasi Presensi #' . $location->id]);
                    }
                });

            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                UPDATE lokasi_absens
                LEFT JOIN divisis ON divisis.id = lokasi_absens.divisi_id
                SET lokasi_absens.nama_lokasi = COALESCE(
                    NULLIF(lokasi_absens.nama_lokasi, ''),
                    CONCAT('Lokasi ', COALESCE(divisis.nama_divisi, lokasi_absens.id))
                )
            ");
        }
    }
};
