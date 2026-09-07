<?php

namespace Tests\Feature;

use App\Exports\CvMakerProgressExport;
use App\Models\User;
use App\Services\CvMaker\CvMakerCompareService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CvMakerProgressExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik')->primary();
            $table->string('nama_karyawan');
            $table->string('area_kerja');
            $table->integer('departemen_id')->nullable();
            $table->integer('divisi_id')->nullable();
            $table->string('posisi');
            $table->string('status_resign');
        });
        Schema::create('departemens', function (Blueprint $table) {
            $table->integer('id');
            $table->string('departemen');
        });
        Schema::create('divisis', function (Blueprint $table) {
            $table->integer('id');
            $table->string('nama_divisi');
        });
        Schema::create('cv_maker_progress_statuses', function (Blueprint $table) {
            $table->string('employee_nik');
            $table->integer('cv_user_id')->nullable();
            $table->integer('cv_profile_id')->nullable();
            $table->boolean('is_complete')->default(false);
            $table->string('current_step_label')->nullable();
            $table->integer('completed_step_count')->default(0);
            $table->integer('total_step_count')->default(8);
            $table->timestamp('last_synced_at')->nullable();
        });
    }

    public function test_export_preserves_scope_filters_and_distinguishes_missing_snapshots(): void
    {
        foreach (range(1, 7) as $id) {
            DB::table('employees')->insert(['nik' => '000' . $id, 'nama_karyawan' => '=1+1',
                'area_kerja' => 'VDNI', 'posisi' => 'PENGAWAS PRODUKSI',
                'status_resign' => $id === 7 ? 'PHK' : 'AKTIF']);
        }
        foreach (range(2, 6) as $id) {
            DB::table('cv_maker_progress_statuses')->insert(['employee_nik' => '000' . $id,
                'cv_user_id' => $id >= 3 ? $id : null, 'cv_profile_id' => $id >= 4 ? $id : null,
                'is_complete' => $id === 5]);
        }
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('applyEmployeeScope')->andReturnUsing(function ($query) {
            return $query->where('employees.nik', '<>', '0006');
        });
        $query = app(CvMakerCompareService::class)->filteredEmployeeQuery(new Request([
            'jabatan_hris' => ['PENGAWAS'], 'status_resign' => 'AKTIF', 'cv_progress_status' => 'not_complete',
        ]), $user);
        $rows = iterator_to_array((new CvMakerProgressExport($query))->generator());
        $this->assertSame(['0001', '0002', '0003', '0004'], array_column($rows, 0));
        $this->assertSame(['Snapshot belum tersedia (status belum diketahui)', 'Belum memiliki akun CV',
            'Profil CV belum dibuat', 'Dalam progress / belum lengkap'], array_column($rows, 7));

        $bytes = \Maatwebsite\Excel\Facades\Excel::raw(new CvMakerProgressExport($query), \Maatwebsite\Excel\Excel::XLSX);
        $file = tempnam(sys_get_temp_dir(), 'cv-export-test-');
        try {
            file_put_contents($file, $bytes);
            $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
            $sheet = $book->getActiveSheet();
            $this->assertSame(5, $sheet->getHighestRow());
            $this->assertSame('0001', $sheet->getCell('A2')->getValue());
            $this->assertSame('s', $sheet->getCell('A2')->getDataType());
            $this->assertSame('=1+1', $sheet->getCell('B2')->getValue());
            $this->assertSame('s', $sheet->getCell('B2')->getDataType());
            $book->disconnectWorksheets();
        } finally {
            unlink($file);
        }
    }

    public function test_export_reads_across_chunks_without_duplicates_or_missing_employees(): void
    {
        foreach (range(1, 251) as $id) {
            DB::table('employees')->insert(['nik' => sprintf('%06d', $id), 'nama_karyawan' => 'Test',
                'area_kerja' => 'VDNI', 'posisi' => 'PENGAWAS', 'status_resign' => 'AKTIF']);
        }
        $rows = iterator_to_array((new CvMakerProgressExport(\App\Models\Employee::query()))->generator());
        $this->assertCount(251, $rows);
        $this->assertCount(251, array_unique(array_column($rows, 0)));
        $this->assertSame('000251', $rows[250][0]);
    }

    public function test_export_validates_filters_and_route_has_access_protection(): void
    {
        $rules = (new \App\Http\Requests\CvMaker\ExportCvMakerProgressRequest())->rules();
        $this->assertTrue(\Illuminate\Support\Facades\Validator::make([
            'cv_progress_status' => 'not_complete', 'jabatan_hris' => ['PENGAWAS'],
        ], $rules)->passes());
        $this->assertTrue(\Illuminate\Support\Facades\Validator::make([
            'cv_progress_status' => ['invalid'], 'jabatan_hris' => ['invalid'],
        ], $rules)->fails());
        $route = app('router')->getRoutes()->getByName('cv-maker-compare.export');
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('menu:cv_maker_compare', $route->gatherMiddleware());
        $this->assertContains('role:Super Admin,HR,HOD,Manager,Supervisor,Admin Divisi', $route->gatherMiddleware());
    }
}
