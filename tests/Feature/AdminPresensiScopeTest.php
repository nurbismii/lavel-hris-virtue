<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PresensiController;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\Presensi\OvertimeOrderService;
use App\Services\Presensi\WorkScheduleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminPresensiScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
        $this->seedOrganization();

        $this->app->instance(WorkScheduleService::class, new class {
            public function buildOffStatusMap()
            {
                return [];
            }
        });

        $this->app->instance(OvertimeOrderService::class, new class {
            public function buildAcceptedAlphaMap()
            {
                return [];
            }
        });
    }

    public function test_index_filters_departments_divisions_and_areas_to_user_scope(): void
    {
        $request = $this->scopedRequest($this->makeScopedUser('HOD', 'HOD001'));

        $view = app(PresensiController::class)->index($request);

        $this->assertSame([10], $view->getData()['departemens']->pluck('id')->values()->all());
        $this->assertSame([101, 102], $view->getData()['divisis']->pluck('id')->values()->all());
        $this->assertSame(['VDNI'], $view->getData()['areas']->pluck('kode_perusahaan')->values()->all());
    }

    public function test_datatable_query_does_not_return_employees_outside_department_scope(): void
    {
        $request = $this->scopedRequest($this->makeScopedUser('HOD', 'HOD001'), [
            'departemen' => 20,
            'cutoff_month' => '2026-05',
            'start' => 0,
            'length' => 50,
        ]);

        $payload = $this->jsonPayload(app(PresensiController::class)->dataPresensi($request));

        $this->assertSame([], collect($payload['data'])->pluck('nik_karyawan')->all());
    }

    public function test_datatable_query_restricts_admin_divisi_to_assigned_divisions(): void
    {
        $request = $this->scopedRequest($this->makeScopedUser('Admin Divisi', 'ADM101', [101]), [
            'departemen' => 10,
            'cutoff_month' => '2026-05',
            'start' => 0,
            'length' => 50,
        ]);

        $payload = $this->jsonPayload(app(PresensiController::class)->dataPresensi($request));

        $this->assertSame(['ADM101', 'EMP101', 'HOD001'], collect($payload['data'])->pluck('nik_karyawan')->values()->all());
    }

    public function test_datatable_attendance_map_uses_filtered_page_employees(): void
    {
        DB::table('absensis')->insert([
            'id' => 50,
            'nik_karyawan' => 'EMP102',
            'tanggal' => '2026-05-01',
            'jam_masuk' => '2026-05-01 08:00:00',
            'jam_istirahat' => null,
            'jam_kembali_istirahat' => null,
            'jam_pulang' => null,
            'status_presensi' => null,
            'status_absen' => 'verified',
        ]);

        $request = $this->scopedRequest($this->makeScopedUser('HOD', 'HOD001'), [
            'departemen' => 10,
            'cutoff_month' => '2026-05',
            'start' => 0,
            'length' => 1,
            'search' => [
                'value' => 'EMP102',
            ],
        ]);

        $payload = $this->jsonPayload(app(PresensiController::class)->dataPresensi($request));

        $this->assertSame(1, $payload['recordsFiltered']);
        $this->assertSame('EMP102', $payload['data'][0]['nik_karyawan']);
        $this->assertSame('08:00', $payload['data'][0]['tanggal_data']['2026-05-01']['m']);
        $this->assertSame('verified', $payload['data'][0]['tanggal_data']['2026-05-01']['m_status']);
    }

    public function test_hod_authorized_department_also_allows_divisions_inside_that_department(): void
    {
        $hod = $this->makeScopedUser('HOD', 'HOD001', [], [30]);

        $this->assertContains('301', $hod->scopedDivisionIds());

        $canAccessHrdEmployee = $hod->applyEmployeeScope(
            Employee::query()->whereKey('EMP301')
        )->exists();

        $this->assertTrue($canAccessHrdEmployee);
    }

    public function test_export_does_not_include_employees_outside_user_scope(): void
    {
        $request = $this->scopedRequest($this->makeScopedUser('HOD', 'HOD001'), [
            'departemen' => 20,
            'cutoff_month' => '2026-05',
        ]);

        $response = app(PresensiController::class)->export($request);

        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString('NIK,Nama', $csv);
        $this->assertStringNotContainsString('EMP201', $csv);
    }

    private function createSchema(): void
    {
        Schema::create('perusahaan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode_perusahaan');
            $table->string('nama_perusahaan');
        });

        Schema::create('departemens', function (Blueprint $table) {
            $table->increments('id');
            $table->string('departemen');
            $table->unsignedInteger('perusahaan_id')->nullable();
        });

        Schema::create('divisis', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_divisi');
            $table->unsignedInteger('departemen_id')->nullable();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik')->primary();
            $table->string('nama_karyawan');
            $table->string('area_kerja')->nullable();
            $table->unsignedInteger('departemen_id')->nullable();
            $table->unsignedInteger('divisi_id')->nullable();
            $table->string('status_resign')->nullable();
            $table->unsignedInteger('work_pattern_id')->nullable();
            $table->date('work_pattern_start_date')->nullable();
        });

        Schema::create('work_patterns', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });

        Schema::create('absensis', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nik_karyawan');
            $table->date('tanggal');
            $table->dateTime('jam_masuk')->nullable();
            $table->dateTime('jam_istirahat')->nullable();
            $table->dateTime('jam_kembali_istirahat')->nullable();
            $table->dateTime('jam_pulang')->nullable();
            $table->string('status_presensi')->nullable();
            $table->string('status_absen')->nullable();
        });

        Schema::create('presensi_verifications', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('presensi_id');
            $table->string('attendance_type');
            $table->string('status');
        });

        Schema::create('national_holidays', function (Blueprint $table) {
            $table->increments('id');
            $table->date('holiday_date');
            $table->string('holiday_name')->nullable();
        });
    }

    private function seedOrganization(): void
    {
        DB::table('perusahaan')->insert([
            ['id' => 1, 'kode_perusahaan' => 'VDNI', 'nama_perusahaan' => 'VDNI'],
            ['id' => 2, 'kode_perusahaan' => 'OSS', 'nama_perusahaan' => 'OSS'],
        ]);

        DB::table('departemens')->insert([
            ['id' => 10, 'departemen' => 'Produksi', 'perusahaan_id' => 1],
            ['id' => 20, 'departemen' => 'Finance', 'perusahaan_id' => 2],
            ['id' => 30, 'departemen' => 'HRD', 'perusahaan_id' => 1],
        ]);

        DB::table('divisis')->insert([
            ['id' => 101, 'nama_divisi' => 'Smelter A', 'departemen_id' => 10],
            ['id' => 102, 'nama_divisi' => 'Smelter B', 'departemen_id' => 10],
            ['id' => 201, 'nama_divisi' => 'Payroll', 'departemen_id' => 20],
            ['id' => 301, 'nama_divisi' => 'HR Operations', 'departemen_id' => 30],
        ]);

        DB::table('employees')->insert([
            ['nik' => 'HOD001', 'nama_karyawan' => 'HOD Produksi', 'area_kerja' => 'VDNI', 'departemen_id' => 10, 'divisi_id' => 101, 'status_resign' => 'AKTIF'],
            ['nik' => 'ADM101', 'nama_karyawan' => 'Admin Divisi', 'area_kerja' => 'VDNI', 'departemen_id' => 10, 'divisi_id' => 101, 'status_resign' => 'AKTIF'],
            ['nik' => 'EMP101', 'nama_karyawan' => 'Staff Smelter A', 'area_kerja' => 'VDNI', 'departemen_id' => 10, 'divisi_id' => 101, 'status_resign' => 'AKTIF'],
            ['nik' => 'EMP102', 'nama_karyawan' => 'Staff Smelter B', 'area_kerja' => 'VDNI', 'departemen_id' => 10, 'divisi_id' => 102, 'status_resign' => 'AKTIF'],
            ['nik' => 'EMP201', 'nama_karyawan' => 'Staff Finance', 'area_kerja' => 'OSS', 'departemen_id' => 20, 'divisi_id' => 201, 'status_resign' => 'AKTIF'],
            ['nik' => 'EMP301', 'nama_karyawan' => 'Staff HRD', 'area_kerja' => 'VDNI', 'departemen_id' => null, 'divisi_id' => 301, 'status_resign' => 'AKTIF'],
        ]);
    }

    private function scopedRequest(User $user, array $query = []): Request
    {
        $request = Request::create('/admin/fetch/data-presensi', 'GET', $query);
        $request->setUserResolver(fn($guard = null) => $user);
        $this->be($user);
        $this->app->instance('request', $request);

        return $request;
    }

    private function makeScopedUser(string $roleName, string $nik, array $authorizedDivisiIds = [], array $authorizedDepartemenIds = []): User
    {
        $user = new User();
        $user->id = 'user-' . $nik;
        $user->name = $roleName . ' User';
        $user->email = strtolower($nik) . '@example.test';
        $user->nik_karyawan = $nik;
        $user->authorized_divisi_ids = $authorizedDivisiIds;
        $user->authorized_departemen_ids = $authorizedDepartemenIds;
        $user->setRelation('role', new Role(['permission_role' => $roleName]));
        $user->setRelation('employee', Employee::query()->whereKey($nik)->first());

        return $user;
    }

    private function jsonPayload($response): array
    {
        return json_decode($response->getContent(), true) ?: [];
    }
}
