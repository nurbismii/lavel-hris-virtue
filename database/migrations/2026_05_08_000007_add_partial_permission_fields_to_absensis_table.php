<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('absensis')) {
            return;
        }

        if (!Schema::hasColumn('absensis', 'partial_permission_type')) {
            Schema::table('absensis', function (Blueprint $table) {
                $table->string('partial_permission_type', 40)->nullable()->after('status_presensi');
            });
        }

        if (!Schema::hasColumn('absensis', 'partial_permission_period')) {
            Schema::table('absensis', function (Blueprint $table) {
                $table->string('partial_permission_period', 20)->nullable()->after('partial_permission_type');
            });
        }

        if (!Schema::hasColumn('absensis', 'partial_permission_note')) {
            Schema::table('absensis', function (Blueprint $table) {
                $table->string('partial_permission_note', 500)->nullable()->after('partial_permission_period');
            });
        }

        if (!Schema::hasColumn('absensis', 'partial_permission_correction_id')) {
            Schema::table('absensis', function (Blueprint $table) {
                $table->unsignedBigInteger('partial_permission_correction_id')->nullable()->after('partial_permission_note');
            });
        }

        Schema::table('absensis', function (Blueprint $table) {
            $table->index(['partial_permission_type', 'tanggal'], 'absensis_partial_permission_type_date_index');
            $table->index(['partial_permission_correction_id'], 'absensis_partial_permission_correction_index');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('absensis')) {
            return;
        }

        Schema::table('absensis', function (Blueprint $table) {
            $table->dropIndex('absensis_partial_permission_type_date_index');
            $table->dropIndex('absensis_partial_permission_correction_index');
        });

        Schema::table('absensis', function (Blueprint $table) {
            foreach ([
                'partial_permission_correction_id',
                'partial_permission_note',
                'partial_permission_period',
                'partial_permission_type',
            ] as $column) {
                if (Schema::hasColumn('absensis', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
