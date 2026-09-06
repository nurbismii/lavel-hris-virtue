<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Services\CvMaker\CvMakerDashboardService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CvMakerDashboardServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik')->primary();
            $table->string('nama_karyawan');
            $table->integer('departemen_id')->nullable();
            $table->string('area_kerja');
            $table->string('status_resign')->nullable();
        });
        Schema::create('departemens', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('departemen');
        });
        Schema::create('cv_maker_progress_statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('employee_nik')->unique();
            $table->integer('cv_user_id')->nullable();
            $table->integer('cv_profile_id')->nullable();
            $table->boolean('is_complete')->default(false);
            $table->boolean('needs_reminder')->default(false);
            $table->string('review_status')->default('unreviewed');
            $table->integer('current_step')->default(1);
            $table->string('current_step_label')->default('Data Pribadi');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
        });
    }

    public function test_summary_preserves_scope_and_distinguishes_unknown_from_incomplete(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('departemens')->insert(['id' => 1, 'departemen' => 'HR']);
        foreach (range(1, 6) as $id) {
            DB::table('employees')->insert(['nik' => 'E' . $id, 'nama_karyawan' => 'Employee ' . $id,
                'departemen_id' => 1, 'area_kerja' => $id === 6 ? 'OUTSIDE' : 'VDNI']);
        }
        $this->snapshot('E2'); // Known missing account, unlike E1 which has no snapshot.
        $this->snapshot('E3', ['cv_user_id' => 3]);
        $this->snapshot('E4', ['cv_user_id' => 4, 'cv_profile_id' => 4, 'needs_reminder' => true]);
        $this->snapshot('E5', ['cv_user_id' => 5, 'cv_profile_id' => 5, 'is_complete' => true, 'review_status' => 'completed']);
        $this->snapshot('E6', ['cv_user_id' => 6, 'cv_profile_id' => 6, 'needs_reminder' => true]);
        $query = Employee::query()->select('employees.nik')->where('area_kerja', 'VDNI')->orderBy('nama_karyawan');
        $result = app(CvMakerDashboardService::class)->summarize($query);
        $this->assertSame(5, (int) $result['summary']['total']);
        foreach (['not_synced', 'no_account', 'no_profile', 'in_progress', 'complete', 'reminder'] as $key) {
            $this->assertSame(1, (int) $result['summary'][$key], $key);
        }
        $this->assertCount(1, $result['priorities']);
        $this->assertSame('Employee 4', $result['priorities'][0]['name']);
        $this->assertSame(5, (int) $result['departments'][0]->total);
        $this->assertSame(1, (int) $result['steps'][0]->total);
        $this->assertSame(['employees.nik'], $query->getQuery()->columns);
        $this->assertCount(1, $query->getQuery()->orders);
    }

    public function test_empty_scope_returns_no_data_from_other_employees(): void
    {
        $result = app(CvMakerDashboardService::class)->summarize(Employee::query()->whereRaw('1 = 0'));
        $this->assertSame(0, (int) $result['summary']['total']);
        $this->assertEmpty($result['priorities']);
        $this->assertEmpty($result['departments']);
        $this->assertSame(0, array_sum(array_column($result['reviews'], 'total')));
    }

    private function snapshot(string $nik, array $values = []): void
    {
        DB::table('cv_maker_progress_statuses')->insert(array_merge([
            'employee_nik' => $nik, 'last_synced_at' => '2026-09-06 08:00:00',
        ], $values));
    }

    public function test_endpoint_defaults_to_active_and_can_select_inactive_or_all(): void
    {
        foreach (['AKTIF', 'PHK', 'RESIGN SESUAI PROSEDUR', null] as $index => $status) {
            DB::table('employees')->insert(['nik' => 'S' . $index, 'nama_karyawan' => 'Status ' . $index,
                'area_kerja' => 'VDNI', 'status_resign' => $status]);
        }
        DB::table('employees')->insert(['nik' => 'OTHER', 'nama_karyawan' => 'Outside',
            'area_kerja' => 'OTHER', 'status_resign' => 'AKTIF']);

        $user = \Mockery::mock(\App\Models\User::class)->makePartial();
        $user->shouldReceive('applyEmployeeScope')->andReturnUsing(function ($query) { return $query; });
        $user->shouldReceive('hasMenuAccess')->with('cv_maker_compare')->andReturn(false);
        $controller = app(\App\Http\Controllers\Admin\CvMakerDashboardController::class);
        foreach (['default' => 1, 'active' => 1, 'inactive' => 2, 'all' => 3] as $status => $expected) {
            $request = \Illuminate\Http\Request::create('/admin/cv-maker-dashboard/data', 'GET',
                $status === 'default' ? [] : ['employment_status' => $status]);
            $request->setUserResolver(function () use ($user) { return $user; });
            $response = $controller->data($request, app(\App\Services\CvMaker\CvMakerCompareService::class), app(CvMakerDashboardService::class));
            $this->assertSame($expected, (int) $response->getData(true)['data']['summary']['total'], $status);
        }
    }

    public function test_company_boundary_applies_without_a_company_filter_and_preserves_scope(): void
    {
        foreach (['VDNI', 'VDNIP', 'OTHER'] as $index => $company) {
            DB::table('employees')->insert(['nik' => 'C' . $index, 'nama_karyawan' => $company,
                'area_kerja' => $company, 'departemen_id' => null]);
            $this->snapshot('C' . $index, ['cv_user_id' => $index + 1, 'cv_profile_id' => $index + 1, 'needs_reminder' => true]);
        }
        $service = app(CvMakerDashboardService::class);
        $result = $service->summarize(Employee::query());
        $this->assertSame(2, (int) $result['summary']['total']);
        $this->assertEqualsCanonicalizing(['VDNI', 'VDNIP'], $result['priorities']->pluck('name')->all());
        $this->assertSame(2, (int) $result['summary']['reminder']);
        $outside = $service->summarize(Employee::query()->where('employees.area_kerja', 'OTHER'));
        $this->assertSame(0, (int) $outside['summary']['total']);
        $this->assertEmpty($outside['priorities']);
        $scoped = $service->summarize(Employee::query()->where('employees.nik', 'C1'));
        $this->assertSame(1, (int) $scoped['summary']['total']);
        $this->assertSame('VDNIP', $scoped['priorities'][0]['name']);
    }

    public function test_dashboard_routes_have_separate_menu_authorization(): void
    {
        foreach (['cv-maker-dashboard.index', 'cv-maker-dashboard.data'] as $name) {
            $middleware = app('router')->getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains('menu:cv_maker_dashboard', $middleware);
            $this->assertContains('role:Super Admin,HR,HOD,Manager,Supervisor,Admin Divisi', $middleware);
            $this->assertNotContains('menu:cv_maker_compare', $middleware);
        }
    }

    public function test_menu_migration_preserves_restrictions_and_is_idempotent(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('permission_role');
            $table->text('menu_permissions')->nullable();
        });
        DB::table('roles')->insert([
            ['permission_role' => 'HR', 'menu_permissions' => '["cv_maker_compare","data_karyawan"]'],
            ['permission_role' => 'HOD', 'menu_permissions' => '[]'],
        ]);
        $migration = require database_path('migrations/2026_09_06_000001_append_cv_maker_dashboard_menu_to_roles.php');
        $migration->up();
        $migration->up();
        $this->assertSame(['cv_maker_compare', 'data_karyawan', 'cv_maker_dashboard'],
            json_decode(DB::table('roles')->where('permission_role', 'HR')->value('menu_permissions'), true));
        $this->assertSame('[]', DB::table('roles')->where('permission_role', 'HOD')->value('menu_permissions'));
    }
}
