<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_levels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('rank');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'rank'], 'job_levels_active_rank_idx');
        });

        Schema::create('job_titles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('name_zh', 255)->nullable();
            $table->string('normalized_name', 255)->unique();
            $table->unsignedBigInteger('job_level_id');
            $table->boolean('is_active')->default(true);
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->foreign('job_level_id')->references('id')->on('job_levels')->restrictOnDelete();
            $table->index(['job_level_id', 'is_active'], 'job_titles_level_active_idx');
        });

        Schema::create('job_title_aliases', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('job_title_id');
            $table->string('alias', 255);
            $table->string('normalized_alias', 255)->unique();
            $table->timestamps();

            $table->foreign('job_title_id')->references('id')->on('job_titles')->cascadeOnDelete();
            $table->index('job_title_id');
        });

        Schema::create('organization_positions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 80)->unique();
            $table->unsignedBigInteger('perusahaan_id')->nullable();
            $table->unsignedBigInteger('departemen_id');
            $table->unsignedBigInteger('divisi_id')->nullable();
            $table->unsignedBigInteger('job_title_id');
            $table->unsignedBigInteger('job_level_id')->nullable();
            $table->unsignedBigInteger('parent_position_id')->nullable();
            $table->unsignedSmallInteger('planned_headcount')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->foreign('job_title_id')->references('id')->on('job_titles')->restrictOnDelete();
            $table->foreign('job_level_id')->references('id')->on('job_levels')->restrictOnDelete();
            $table->foreign('parent_position_id')->references('id')->on('organization_positions')->nullOnDelete();
            $table->index(['departemen_id', 'divisi_id', 'is_active'], 'org_positions_scope_active_idx');
            $table->index(['parent_position_id', 'sort_order'], 'org_positions_parent_sort_idx');
            $table->index(['perusahaan_id', 'departemen_id'], 'org_positions_company_department_idx');
        });

        Schema::create('employee_position_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('employee_nik', 32);
            $table->unsignedBigInteger('organization_position_id');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('source', 30)->default('vpeople');
            $table->string('reference_number', 120)->nullable();
            $table->string('notes', 500)->nullable();
            $table->string('created_by_user_id', 36)->nullable();
            $table->string('ended_by_user_id', 36)->nullable();
            $table->timestamps();

            $table->foreign('organization_position_id')->references('id')->on('organization_positions')->restrictOnDelete();
            $table->index(['employee_nik', 'status', 'effective_from'], 'employee_position_employee_status_idx');
            $table->index(['organization_position_id', 'status'], 'employee_position_position_status_idx');
            $table->index(['status', 'effective_from', 'effective_until'], 'employee_position_period_idx');
        });

        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'job_title_id')) {
                $table->unsignedBigInteger('job_title_id')->nullable()->after('jabatan');
                $table->index('job_title_id', 'employees_job_title_idx');
            }

            if (!Schema::hasColumn('employees', 'organization_position_id')) {
                $table->unsignedBigInteger('organization_position_id')->nullable()->after('job_title_id');
                $table->index('organization_position_id', 'employees_org_position_idx');
            }

            if (!Schema::hasColumn('employees', 'reports_to_nik')) {
                $table->string('reports_to_nik', 32)->nullable()->after('organization_position_id');
                $table->index('reports_to_nik', 'employees_reports_to_idx');
            }
        });

        $this->seedInitialLevelsAndTitles();
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'reports_to_nik')) {
                $table->dropIndex('employees_reports_to_idx');
                $table->dropColumn('reports_to_nik');
            }

            if (Schema::hasColumn('employees', 'organization_position_id')) {
                $table->dropIndex('employees_org_position_idx');
                $table->dropColumn('organization_position_id');
            }

            if (Schema::hasColumn('employees', 'job_title_id')) {
                $table->dropIndex('employees_job_title_idx');
                $table->dropColumn('job_title_id');
            }
        });

        Schema::dropIfExists('employee_position_assignments');
        Schema::dropIfExists('organization_positions');
        Schema::dropIfExists('job_title_aliases');
        Schema::dropIfExists('job_titles');
        Schema::dropIfExists('job_levels');
    }

    private function seedInitialLevelsAndTitles(): void
    {
        $now = now();

        foreach (range(1, 8) as $rank) {
            DB::table('job_levels')->insert([
                'code' => 'L' . $rank,
                'name' => 'Level ' . $rank,
                'rank' => $rank,
                'sort_order' => 8 - $rank,
                'description' => 'Level awal berdasarkan file LEVEL JABATAN VDNI.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $levelIds = DB::table('job_levels')->pluck('id', 'rank');
        $titles = [
            ['code' => 'WAKIL_KEPALA_DEPT_GA', 'level' => 8, 'name' => 'WAKIL KEPALA DEPARTEMEN GENERAL AFFAIR', 'zh' => '综合部副部长'],
            ['code' => 'KEPALA_PRODUKSI', 'level' => 7, 'name' => 'KEPALA PRODUKSI', 'zh' => '科长'],
            ['code' => 'WAKIL_KEPALA_PRODUKSI', 'level' => 6, 'name' => 'WAKIL KEPALA PRODUKSI', 'zh' => '副科长'],
            ['code' => 'SUPERVISOR', 'level' => 5, 'name' => 'SUPERVISOR', 'zh' => '调度'],
            ['code' => 'KEPALA_KORDINATOR', 'level' => 5, 'name' => 'KEPALA KORDINATOR', 'zh' => '调度'],
            ['code' => 'WAKIL_SUPERVISOR', 'level' => 4, 'name' => 'WAKIL SUPERVISOR', 'zh' => '副调度'],
            ['code' => 'WAKIL_KEPALA_TUNGKU', 'level' => 4, 'name' => 'WAKIL KEPALA TUNGKU', 'zh' => '副炉长'],
            ['code' => 'WAKIL_KEPALA_KORDINATOR', 'level' => 4, 'name' => 'WAKIL KEPALA KORDINATOR', 'zh' => '副调度'],
            ['code' => 'KOORDINATOR', 'level' => 3, 'name' => 'KOORDINATOR', 'zh' => '大班长'],
            ['code' => 'WAKIL_KOORDINATOR', 'level' => 3, 'name' => 'WAKIL KOORDINATOR', 'zh' => '副大班长'],
            ['code' => 'PENGAWAS', 'level' => 2, 'name' => 'PENGAWAS', 'zh' => '班长'],
            ['code' => 'WAKIL_PENGAWAS', 'level' => 2, 'name' => 'WAKIL PENGAWAS', 'zh' => '副班长'],
            ['code' => 'STAFF', 'level' => 2, 'name' => 'STAFF', 'zh' => '职员'],
            ['code' => 'ADMIN', 'level' => 1, 'name' => 'ADMIN', 'zh' => '文员'],
        ];

        foreach ($titles as $title) {
            $normalizedName = $this->normalize($title['name']);
            $titleId = DB::table('job_titles')->insertGetId([
                'code' => $title['code'],
                'name' => $title['name'],
                'name_zh' => $title['zh'],
                'normalized_name' => $normalizedName,
                'job_level_id' => $levelIds[$title['level']],
                'is_active' => true,
                'description' => 'Data awal dari file LEVEL JABATAN VDNI.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (array_unique([$title['name'], $title['name'] . ' ' . $title['zh']]) as $alias) {
                DB::table('job_title_aliases')->insert([
                    'job_title_id' => $titleId,
                    'alias' => $alias,
                    'normalized_alias' => $this->normalize($alias),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function normalize(string $value): string
    {
        return mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', $value)), 'UTF-8');
    }
};
