<?php

namespace Tests\Feature;

use App\Http\Requests\KaryawanRequest\FilterKaryawanRequest;
use App\Services\Karyawan\KaryawanService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class KaryawanFiltersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('sqlite');
        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik')->primary();
            foreach (['nama_karyawan', 'area_kerja', 'departemen_id', 'divisi_id', 'status_resign', 'posisi', 'jabatan', 'status_karyawan', 'pendidikan_terakhir', 'jenis_kelamin', 'entry_date', 'no_ktp', 'photo_path', 'ktp_path', 'kk_path', 'sim_path', 'sio_path', 'face_reference_path'] as $column) {
                $table->string($column)->nullable();
            }
        });
        Schema::create('departemens', function (Blueprint $table) {
            $table->increments('id');
            $table->string('departemen');
        });
        Schema::create('divisis', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_divisi');
        });
        foreach ([
            ['001', 'Budi', 'Operator', 'Staff', 'PKWT', 'SMA', 'L', '2026-01-15', '1'],
            ['002', 'Sari', 'Admin', 'Staff', 'PKWTT', 'S1', 'P', '2026-02-15', '1'],
            ['003', 'Andi', 'Operator', 'Supervisor', 'PKWT', 'SMA', 'L', '2026-01-16', '2'],
        ] as $row) {
            DB::table('employees')->insert(array_merge(array_combine(
                ['nik', 'nama_karyawan', 'posisi', 'jabatan', 'status_karyawan', 'pendidikan_terakhir', 'jenis_kelamin', 'entry_date', 'departemen_id'], $row
            ), ['area_kerja' => 'VDNI', 'status_resign' => 'AKTIF']));
        }
    }

    public function test_each_filter_and_combination_with_search(): void
    {
        foreach ([
            ['jabatan' => 'Supervisor'], ['posisi' => 'Operator', 'departemen' => '2'],
            ['status_karyawan' => 'PKWT', 'entry_date_from' => '2026-01-16'],
        ] as $filters) {
            $this->assertSame(['003'], $this->rows($filters));
        }
        $this->assertSame(['002'], $this->rows(['pendidikan_terakhir' => 'S1', 'jenis_kelamin' => 'P']));
        $this->assertSame(['001'], $this->rows(['posisi' => 'Operator', 'jabatan' => 'Staff', 'status_resign' => 'AKTIF', 'area' => ['VDNI'], 'search' => ['value' => 'Budi']]));
        $this->assertSame([], $this->rows(['posisi' => 'Tidak ada']));
        $this->assertSame(['001', '002', '003'], $this->rows([]));
    }

    public function test_date_boundaries_include_the_last_day(): void
    {
        DB::table('employees')->where('nik', '001')->update(['entry_date' => '2026-01-15 23:59:59']);
        $this->assertSame(['001'], $this->rows(['entry_date_from' => '2026-01-15', 'entry_date_to' => '2026-01-15']));
        $this->assertSame(['001', '003'], $this->rows(['entry_date_to' => '2026-01-16']));
    }

    public function test_filters_and_options_preserve_access_scope(): void
    {
        $this->assertSame([], $this->rows(['jabatan' => 'Supervisor'], '1'));
        $options = app(KaryawanService::class)->filterOptions($this->scopeUser('1'));
        $this->assertSame(['Staff'], $options['jabatan']['values']->all());
        $this->assertSame(['Admin', 'Operator'], $options['posisi']['values']->all());
    }

    public function test_invalid_filters_are_rejected(): void
    {
        foreach ([
            ['entry_date_from' => '2026-02-01', 'entry_date_to' => '2026-01-01'],
            ['entry_date_from' => 'not-a-date'], ['jabatan' => 'Staff'], ['posisi' => [['invalid']]], ['jenis_kelamin' => 'invalid'],
            ['pendidikan_terakhir' => 'TEKNIK MESIN'],
        ] as $input) {
            $request = FilterKaryawanRequest::create('/karyawan', 'GET', $input);
            $this->assertTrue(Validator::make($input, $request->rules())->fails());
        }
    }

    public function test_filter_controls_render_with_scoped_options(): void
    {
        auth()->setUser($this->scopeUser());
        $template = str_replace("@extends('layouts.app')", '', file_get_contents(resource_path('views/admin/karyawan/index.blade.php')));
        $html = \Illuminate\Support\Facades\Blade::render($template . "\n@yield('content')\n@stack('scripts')", [
            'employeeFilterOptions' => app(KaryawanService::class)->filterOptions($this->scopeUser('1')),
            'departemens' => collect(), 'divisis' => collect(), 'areas' => collect(),
            'canManageMasterData' => false,
        ]);
        foreach (['jabatan', 'posisi', 'status_karyawan', 'pendidikan_terakhir', 'jenis_kelamin', 'entry_date_from', 'entry_date_to'] as $field) {
            $this->assertStringContainsString('id="filter_' . $field . '"', $html);
        }
        $this->assertStringNotContainsString('<option value="Supervisor">', $html);
        $this->assertStringContainsString('multiple data-placeholder="Cari dan pilih jabatan"', $html);
        $this->assertStringContainsString('multiple data-placeholder="Cari dan pilih posisi"', $html);
    }

    public function test_multiple_choices_use_or_within_field_and_and_between_fields(): void
    {
        $this->assertSame(['001', '002'], $this->rows(['posisi' => ['Operator', 'Admin'], 'jabatan' => ['Staff']]));
        $this->assertSame(['001', '003'], $this->rows(['posisi' => ['Operator'], 'jabatan' => ['Staff', 'Supervisor']]));
        $request = FilterKaryawanRequest::create('/karyawan', 'GET', ['posisi' => ['Operator', 'Admin'], 'jabatan' => ['Staff']]);
        $this->assertFalse(Validator::make($request->all(), $request->rules())->fails());
    }

    public function test_job_options_only_come_from_vdni_and_vdnip(): void
    {
        DB::table('employees')->where('nik', '002')->update(['area_kerja' => 'VDNIP']);
        DB::table('employees')->where('nik', '003')->update(['area_kerja' => 'OSS', 'posisi' => 'OSS only']);
        $options = app(KaryawanService::class)->filterOptions($this->scopeUser());
        $this->assertSame(['Admin', 'Operator'], $options['posisi']['values']->all());
        $this->assertSame(['Staff'], $options['jabatan']['values']->all());
        $this->assertSame([], app(KaryawanService::class)->filterOptions($this->scopeUser('2'))['posisi']['values']->all());
    }

    public function test_education_levels_match_bilingual_values_and_do_not_guess_majors(): void
    {
        DB::table('employees')->where('nik', '001')->update(['pendidikan_terakhir' => 'SMA 高中 SEDERAJAT']);
        DB::table('employees')->where('nik', '002')->update(['pendidikan_terakhir' => 'S1 PERTAMBANGAN']);
        DB::table('employees')->where('nik', '003')->update(['pendidikan_terakhir' => 'TEKNIK MESIN']);
        $this->assertSame(['001'], $this->rows(['pendidikan_terakhir' => 'SMA']));
        $this->assertSame(['002'], $this->rows(['pendidikan_terakhir' => 'S1']));
        $this->assertSame([], $this->rows(['pendidikan_terakhir' => 'SMK']));
        $this->assertSame(['SD', 'SMP', 'SMA', 'SMK', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3'], app(KaryawanService::class)->filterOptions($this->scopeUser())['pendidikan_terakhir']['values']->all());
    }

    private function scopeUser($department = null)
    {
        $user = \Mockery::mock(\App\Models\User::class);
        $user->shouldReceive('applyEmployeeScope')->andReturnUsing(function ($query) use ($department) {
            return $department ? $query->where('departemen_id', $department) : $query;
        });
        $user->shouldReceive('canAccessAllEmployees')->andReturn($department === null);

        return $user;
    }

    private function rows(array $filters, $department = null): array
    {
        $request = Request::create('/karyawan', 'GET', array_merge([
            'draw' => 1, 'start' => 0, 'length' => 10,
            'columns' => [['data' => 'nama_karyawan', 'name' => 'nama_karyawan', 'searchable' => 'true', 'orderable' => 'true']],
        ], $filters));
        $this->app->instance('request', $request);
        $response = app(KaryawanService::class)->getDataKaryawan($request, $this->scopeUser($department))->getData(true);
        $this->assertArrayNotHasKey('error', $response);
        $niks = array_column($response['data'], 'nik');
        sort($niks);

        return $niks;
    }
}
