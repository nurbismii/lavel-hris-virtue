<?php

namespace Tests\Feature;

use App\Models\LokasiAbsen;
use App\Models\Role;
use App\Models\User;
use App\Services\Presensi\AttendanceLocationBulkAssignmentService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceLocationBulkAssignmentServiceTest extends TestCase
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
        $this->seedData();
    }

    public function test_bulk_assignment_targets_active_filtered_employees_and_closes_previous_assignment(): void
    {
        $service = app(AttendanceLocationBulkAssignmentService::class);
        $actor = $this->makeSuperAdmin();
        $targetLocation = LokasiAbsen::query()->findOrFail(2);

        $result = $service->assignByFilter(
            $actor,
            $targetLocation,
            [
                'perusahaan_id' => null,
                'departemen_id' => 10,
                'divisi_id' => null,
            ],
            Carbon::parse('2026-05-11'),
            null,
            'Pindah lokasi massal'
        );

        $this->assertSame(2, $result['assigned_count']);
        $this->assertDatabaseHas('employee_attendance_location_assignments', [
            'employee_nik' => 'EMP001',
            'lokasi_absen_id' => 1,
            'effective_until' => '2026-05-10',
        ]);
        $this->assertDatabaseHas('employee_attendance_location_assignments', [
            'employee_nik' => 'EMP001',
            'lokasi_absen_id' => 2,
            'effective_from' => '2026-05-11',
        ]);
        $this->assertDatabaseHas('employee_attendance_location_assignments', [
            'employee_nik' => 'EMP004',
            'lokasi_absen_id' => 2,
            'effective_from' => '2026-05-11',
        ]);
        $this->assertDatabaseMissing('employee_attendance_location_assignments', [
            'employee_nik' => 'EMP002',
            'lokasi_absen_id' => 2,
        ]);
        $this->assertDatabaseMissing('employee_attendance_location_assignments', [
            'employee_nik' => 'EMP003',
            'lokasi_absen_id' => 2,
        ]);
    }

    public function test_bulk_assignment_can_target_specific_niks_inside_same_division(): void
    {
        $service = app(AttendanceLocationBulkAssignmentService::class);
        $actor = $this->makeSuperAdmin();
        $targetLocation = LokasiAbsen::query()->findOrFail(2);

        $result = $service->assignByFilter(
            $actor,
            $targetLocation,
            [
                'perusahaan_id' => null,
                'departemen_id' => 10,
                'divisi_id' => 100,
                'employee_niks' => ['EMP004'],
            ],
            Carbon::parse('2026-05-11')
        );

        $this->assertSame(1, $result['assigned_count']);
        $this->assertDatabaseHas('employee_attendance_location_assignments', [
            'employee_nik' => 'EMP004',
            'lokasi_absen_id' => 2,
            'effective_from' => '2026-05-11',
            'assignment_source' => 'selected_niks',
        ]);
        $this->assertDatabaseMissing('employee_attendance_location_assignments', [
            'employee_nik' => 'EMP001',
            'lokasi_absen_id' => 2,
            'effective_from' => '2026-05-11',
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('perusahaan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode_perusahaan')->nullable();
            $table->string('nama_perusahaan')->nullable();
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
            $table->unsignedInteger('departemen_id')->nullable();
            $table->unsignedInteger('divisi_id')->nullable();
            $table->string('status_resign')->nullable();
        });

        Schema::create('lokasi_absens', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('divisi_id')->nullable();
            $table->string('lat')->nullable();
            $table->string('long')->nullable();
            $table->string('radius')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_attendance_location_assignments', function (Blueprint $table) {
            $table->increments('id');
            $table->string('employee_nik', 32);
            $table->unsignedInteger('lokasi_absen_id');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('assigned_by', 36)->nullable();
            $table->string('batch_id', 36)->nullable();
            $table->string('assignment_source', 40)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['employee_nik', 'effective_from']);
        });

        Schema::create('audit_trails', function (Blueprint $table) {
            $table->increments('id');
            $table->string('event')->nullable();
            $table->string('module')->nullable();
            $table->string('auditable_type')->nullable();
            $table->string('auditable_id')->nullable();
            $table->string('reference_table')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('employee_nik')->nullable();
            $table->string('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_role')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->longText('old_values')->nullable();
            $table->longText('new_values')->nullable();
            $table->longText('metadata')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    private function seedData(): void
    {
        DB::table('perusahaan')->insert([
            ['id' => 1, 'kode_perusahaan' => 'VDNI', 'nama_perusahaan' => 'VDNI'],
        ]);

        DB::table('departemens')->insert([
            ['id' => 10, 'departemen' => 'Produksi', 'perusahaan_id' => 1],
            ['id' => 20, 'departemen' => 'Finance', 'perusahaan_id' => 1],
        ]);

        DB::table('divisis')->insert([
            ['id' => 100, 'nama_divisi' => 'Smelter A', 'departemen_id' => 10],
            ['id' => 200, 'nama_divisi' => 'Finance A', 'departemen_id' => 20],
        ]);

        DB::table('employees')->insert([
            ['nik' => 'EMP001', 'nama_karyawan' => 'Aktif Produksi', 'departemen_id' => 10, 'divisi_id' => 100, 'status_resign' => 'AKTIF'],
            ['nik' => 'EMP002', 'nama_karyawan' => 'Resign Produksi', 'departemen_id' => 10, 'divisi_id' => 100, 'status_resign' => 'RESIGN'],
            ['nik' => 'EMP003', 'nama_karyawan' => 'Aktif Finance', 'departemen_id' => 20, 'divisi_id' => 200, 'status_resign' => 'AKTIF'],
            ['nik' => 'EMP004', 'nama_karyawan' => 'Aktif Produksi Dua', 'departemen_id' => 10, 'divisi_id' => 100, 'status_resign' => 'AKTIF'],
        ]);

        DB::table('lokasi_absens')->insert([
            ['id' => 1, 'divisi_id' => 100, 'lat' => '-3.9900', 'long' => '122.5100', 'radius' => '100'],
            ['id' => 2, 'divisi_id' => 200, 'lat' => '-3.9950', 'long' => '122.5120', 'radius' => '100'],
        ]);

        DB::table('employee_attendance_location_assignments')->insert([
            'employee_nik' => 'EMP001',
            'lokasi_absen_id' => 1,
            'effective_from' => '2026-05-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeSuperAdmin(): User
    {
        $user = new User();
        $user->id = 'admin-1';
        $user->name = 'Super Admin';
        $user->email = 'admin@example.test';
        $user->setRelation('role', new Role(['permission_role' => 'Super Admin']));

        return $user;
    }
}
