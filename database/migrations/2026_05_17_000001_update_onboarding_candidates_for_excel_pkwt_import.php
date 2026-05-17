<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('onboarding_candidates')) {
            return;
        }

        Schema::table('onboarding_candidates', function (Blueprint $table) {
            if (!Schema::hasColumn('onboarding_candidates', 'tanggal_akhir_kontrak')) {
                $table->date('tanggal_akhir_kontrak')->nullable()->after('tanggal_mulai_kerja');
            }
        });

        $this->makeVhireCandidateIdNullable();
    }

    public function down(): void
    {
        if (!Schema::hasTable('onboarding_candidates')) {
            return;
        }

        Schema::table('onboarding_candidates', function (Blueprint $table) {
            if (Schema::hasColumn('onboarding_candidates', 'tanggal_akhir_kontrak')) {
                $table->dropColumn('tanggal_akhir_kontrak');
            }
        });
    }

    private function makeVhireCandidateIdNullable(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE onboarding_candidates MODIFY vhire_candidate_id VARCHAR(120) NULL');
    }
};
