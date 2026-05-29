<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\Presensi\AttendanceAnomalyService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceAnomalyServiceTest extends TestCase
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
        $this->seedAttendanceRows();
    }

    public function test_summary_counts_attendance_anomaly_types(): void
    {
        $service = app(AttendanceAnomalyService::class);
        $filters = $service->normalizeFilters([
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-05',
            'anomaly' => 'all',
        ]);

        $summary = $service->summary($this->makeUser('HOD', 'HOD001'), $filters);

        $this->assertSame(4, $summary['total']);
        $this->assertSame(1, $summary['incomplete_clock']);
        $this->assertSame(1, $summary['invalid_sequence']);
        $this->assertSame(1, $summary['face_pending']);
        $this->assertSame(1, $summary['face_rejected']);
        $this->assertSame(1, $summary['face_unverified']);
        $this->assertSame(1, $summary['suspicious_score']);
        $this->assertSame(1, $summary['missing_gps']);
        $this->assertSame(1, $summary['gps_unstable']);
    }

    public function test_datatable_filters_by_anomaly_and_employee_scope(): void
    {
        $service = app(AttendanceAnomalyService::class);
        $filters = $service->normalizeFilters([
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-05',
            'anomaly' => 'face_rejected',
        ]);

        $payload = $service->dataTable($this->makeUser('HOD', 'HOD001'), $filters, [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]);

        $this->assertSame(1, $payload['recordsFiltered']);
        $this->assertSame(['EMP102'], collect($payload['data'])->pluck('nik_karyawan')->all());
        $this->assertStringContainsString('Verifikasi wajah ditolak', $payload['data'][0]['anomaly_labels']);
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
            $table->boolean('face_verified')->nullable();
            $table->string('face_selfie_path')->nullable();
            $table->decimal('face_verification_distance', 8, 6)->nullable();
            $table->integer('security_score')->nullable();
            $table->boolean('is_suspicious')->default(false);
            $table->string('device_info')->nullable();
            $table->string('ip_address')->nullable();
        });

        Schema::create('presensi_verifications', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('presensi_id');
            $table->string('nik_karyawan')->nullable();
            $table->date('tanggal')->nullable();
            $table->string('attendance_type')->nullable();
            $table->string('status');
            $table->decimal('face_verification_distance', 8, 6)->nullable();
            $table->timestamp('submitted_at')->nullable();
        });

        Schema::create('log_presensi', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nik_karyawan');
            $table->date('tanggal');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('long', 10, 7)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->decimal('speed', 8, 2)->nullable();
            $table->timestamp('created_at')->nullable();
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
            ['id' => 20, 'departemen' => 'Finance', 'perusahaan_id' => 1],
        ]);

        DB::table('divisis')->insert([
            ['id' => 101, 'nama_divisi' => 'Smelter A', 'departemen_id' => 10],
            ['id' => 102, 'nama_divisi' => 'Smelter B', 'departemen_id' => 10],
            ['id' => 201, 'nama_divisi' => 'Payroll', 'departemen_id' => 20],
        ]);

        DB::table('employees')->insert([
            ['nik' => 'HR001', 'nama_karyawan' => 'HR User', 'area_kerja' => 'VDNI', 'departemen_id' => 20, 'divisi_id' => 201, 'status_resign' => 'AKTIF'],
            ['nik' => 'HOD001', 'nama_karyawan' => 'HOD Produksi', 'area_kerja' => 'VDNI', 'departemen_id' => 10, 'divisi_id' => 101, 'status_resign' => 'AKTIF'],
            ['nik' => 'EMP101', 'nama_karyawan' => 'Staff Smelter A', 'area_kerja' => 'VDNI', 'departemen_id' => 10, 'divisi_id' => 101, 'status_resign' => 'AKTIF'],
            ['nik' => 'EMP102', 'nama_karyawan' => 'Staff Smelter B', 'area_kerja' => 'VDNI', 'departemen_id' => 10, 'divisi_id' => 102, 'status_resign' => 'AKTIF'],
            ['nik' => 'EMP201', 'nama_karyawan' => 'Staff Payroll', 'area_kerja' => 'VDNI', 'departemen_id' => 20, 'divisi_id' => 201, 'status_resign' => 'AKTIF'],
            ['nik' => 'OUT001', 'nama_karyawan' => 'Outside Company', 'area_kerja' => 'OSS', 'departemen_id' => 20, 'divisi_id' => 201, 'status_resign' => 'AKTIF'],
        ]);
    }

    private function seedAttendanceRows(): void
    {
        DB::table('absensis')->insert([
            [
                'id' => 1,
                'nik_karyawan' => 'EMP101',
                'tanggal' => '2026-05-01',
                'jam_masuk' => '2026-05-01 08:00:00',
                'jam_istirahat' => null,
                'jam_kembali_istirahat' => null,
                'jam_pulang' => null,
                'status_presensi' => null,
                'status_absen' => 'pending_review',
                'face_verified' => 0,
                'security_score' => 70,
                'is_suspicious' => 1,
            ],
            [
                'id' => 2,
                'nik_karyawan' => 'EMP102',
                'tanggal' => '2026-05-02',
                'jam_masuk' => '2026-05-02 08:00:00',
                'jam_istirahat' => '2026-05-02 12:00:00',
                'jam_kembali_istirahat' => '2026-05-02 13:00:00',
                'jam_pulang' => '2026-05-02 17:00:00',
                'status_presensi' => null,
                'status_absen' => 'rejected',
                'face_verified' => 0,
                'security_score' => 95,
                'is_suspicious' => 0,
            ],
            [
                'id' => 3,
                'nik_karyawan' => 'EMP102',
                'tanggal' => '2026-05-03',
                'jam_masuk' => '2026-05-03 08:00:00',
                'jam_istirahat' => '2026-05-03 12:00:00',
                'jam_kembali_istirahat' => '2026-05-03 13:00:00',
                'jam_pulang' => '2026-05-03 17:00:00',
                'status_presensi' => null,
                'status_absen' => null,
                'face_verified' => 0,
                'security_score' => 90,
                'is_suspicious' => 0,
            ],
            [
                'id' => 4,
                'nik_karyawan' => 'EMP101',
                'tanggal' => '2026-05-04',
                'jam_masuk' => '2026-05-04 08:00:00',
                'jam_istirahat' => '2026-05-04 07:55:00',
                'jam_kembali_istirahat' => '2026-05-04 13:00:00',
                'jam_pulang' => '2026-05-04 17:00:00',
                'status_presensi' => null,
                'status_absen' => 'verified',
                'face_verified' => 1,
                'security_score' => 95,
                'is_suspicious' => 0,
            ],
            [
                'id' => 5,
                'nik_karyawan' => 'EMP201',
                'tanggal' => '2026-05-02',
                'jam_masuk' => '2026-05-02 08:00:00',
                'jam_istirahat' => null,
                'jam_kembali_istirahat' => null,
                'jam_pulang' => null,
                'status_presensi' => null,
                'status_absen' => 'rejected',
                'face_verified' => 0,
                'security_score' => 90,
                'is_suspicious' => 0,
            ],
            [
                'id' => 6,
                'nik_karyawan' => 'OUT001',
                'tanggal' => '2026-05-02',
                'jam_masuk' => '2026-05-02 08:00:00',
                'jam_istirahat' => null,
                'jam_kembali_istirahat' => null,
                'jam_pulang' => null,
                'status_presensi' => null,
                'status_absen' => 'rejected',
                'face_verified' => 0,
                'security_score' => 90,
                'is_suspicious' => 0,
            ],
        ]);

        DB::table('log_presensi')->insert([
            ['nik_karyawan' => 'EMP101', 'tanggal' => '2026-05-01', 'lat' => -4.1, 'long' => 122.1, 'accuracy' => 10, 'speed' => 0, 'created_at' => '2026-05-01 08:00:10'],
            ['nik_karyawan' => 'EMP102', 'tanggal' => '2026-05-03', 'lat' => -4.1, 'long' => 122.1, 'accuracy' => 80, 'speed' => 0, 'created_at' => '2026-05-03 08:00:10'],
            ['nik_karyawan' => 'EMP101', 'tanggal' => '2026-05-04', 'lat' => -4.1, 'long' => 122.1, 'accuracy' => 10, 'speed' => 0, 'created_at' => '2026-05-04 08:00:10'],
        ]);
    }

    private function makeUser(string $roleName, string $nik): User
    {
        $user = new User();
        $user->id = 'user-' . $nik;
        $user->name = $roleName . ' User';
        $user->email = strtolower($nik) . '@example.test';
        $user->nik_karyawan = $nik;
        $user->setRelation('role', new Role(['permission_role' => $roleName]));
        $user->setRelation('employee', Employee::query()->whereKey($nik)->first());

        return $user;
    }
}
