<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $columns = [
        'departemen_id',
        'divisi_id',
        'provinsi_id',
        'kabupaten_id',
        'kecamatan_id',
        'kelurahan_id',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('onboarding_candidates')) {
            return;
        }

        Schema::table('onboarding_candidates', function (Blueprint $table) {
            if (!Schema::hasColumn('onboarding_candidates', 'departemen_id')) {
                $table->unsignedBigInteger('departemen_id')->nullable()->after('departemen');
            }

            if (!Schema::hasColumn('onboarding_candidates', 'divisi_id')) {
                $table->unsignedBigInteger('divisi_id')->nullable()->after('divisi');
            }

            if (!Schema::hasColumn('onboarding_candidates', 'provinsi_id')) {
                $table->unsignedBigInteger('provinsi_id')->nullable()->after('alamat_domisili');
            }

            if (!Schema::hasColumn('onboarding_candidates', 'kabupaten_id')) {
                $table->unsignedBigInteger('kabupaten_id')->nullable()->after('provinsi_id');
            }

            if (!Schema::hasColumn('onboarding_candidates', 'kecamatan_id')) {
                $table->unsignedBigInteger('kecamatan_id')->nullable()->after('kabupaten_id');
            }

            if (!Schema::hasColumn('onboarding_candidates', 'kelurahan_id')) {
                $table->unsignedBigInteger('kelurahan_id')->nullable()->after('kecamatan_id');
            }
        });

        Schema::table('onboarding_candidates', function (Blueprint $table) {
            if (Schema::hasColumn('onboarding_candidates', 'departemen_id')) {
                $table->index('departemen_id', 'onboarding_candidates_departemen_id_idx');
            }

            if (Schema::hasColumn('onboarding_candidates', 'divisi_id')) {
                $table->index('divisi_id', 'onboarding_candidates_divisi_id_idx');
            }

            if (Schema::hasColumn('onboarding_candidates', 'provinsi_id')) {
                $table->index('provinsi_id', 'onboarding_candidates_provinsi_id_idx');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('onboarding_candidates')) {
            return;
        }

        Schema::table('onboarding_candidates', function (Blueprint $table) {
            foreach ([
                'onboarding_candidates_departemen_id_idx',
                'onboarding_candidates_divisi_id_idx',
                'onboarding_candidates_provinsi_id_idx',
            ] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (Throwable $exception) {
                    // Keep rollback tolerant for databases where an index was not created.
                }
            }
        });

        Schema::table('onboarding_candidates', function (Blueprint $table) {
            $columns = array_values(array_filter(
                $this->columns,
                fn(string $column) => Schema::hasColumn('onboarding_candidates', $column)
            ));

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
