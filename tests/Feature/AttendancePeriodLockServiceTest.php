<?php

namespace Tests\Feature;

use App\Models\AttendancePeriodLock;
use App\Models\User;
use App\Services\Presensi\AttendancePeriodLockService;
use App\Services\Presensi\AttendanceStatusService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendancePeriodLockServiceTest extends TestCase
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
        $this->seedBaseData();
    }

    public function test_close_period_blocks_when_pending_approval_exists(): void
    {
        DB::table('cuti_izin')->insert([
            'nik_karyawan' => 'EMP001',
            'tanggal_mulai' => '2026-05-01',
            'tanggal_berakhir' => '2026-05-02',
            'tipe' => 'CUTI',
            'status_hod' => 0,
            'status_hrd' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(AttendancePeriodLockService::class)
            ->closePeriod($this->makeHrUser(), '2026-05', 'Siap payroll');

        $this->assertFalse($result['status']);
        $this->assertSame(1, $result['summary']['pending_cuti_hod']);
        $this->assertSame(0, AttendancePeriodLock::count());
    }

    public function test_close_and_reopen_period_controls_date_guard(): void
    {
        $service = app(AttendancePeriodLockService::class);
        $user = $this->makeHrUser();

        $closeResult = $service->closePeriod($user, '2026-05', 'Data sudah final');

        $this->assertTrue($closeResult['status']);
        $this->assertNotNull($service->guardDate('2026-05-10', 'Presensi'));
        $this->assertNull($service->guardDate('2026-05-16', 'Presensi'));

        $reopenResult = $service->reopenPeriod($closeResult['lock'], $user, 'Ada koreksi payroll resmi');

        $this->assertTrue($reopenResult['status']);
        $this->assertNull($service->guardDate('2026-05-10', 'Presensi'));
        $this->assertDatabaseHas('attendance_period_locks', [
            'period_key' => '2026-05',
            'status' => AttendancePeriodLock::STATUS_REOPENED,
        ]);
    }

    public function test_locked_period_prevents_attendance_status_sync_mutation(): void
    {
        app(AttendancePeriodLockService::class)
            ->closePeriod($this->makeHrUser(), '2026-05', 'Data sudah final');

        DB::table('absensis')->insert([
            'nik_karyawan' => 'EMP001',
            'tanggal' => '2026-05-10',
            'jam_masuk' => '2026-05-10 08:00:00',
            'status_presensi' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('cuti_izin')->insert([
            'nik_karyawan' => 'EMP001',
            'tanggal_mulai' => '2026-05-10',
            'tanggal_berakhir' => '2026-05-10',
            'tipe' => 'CUTI',
            'status_hod' => 1,
            'status_hrd' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $status = app(AttendanceStatusService::class)->syncStatusForDate('EMP001', '2026-05-10');
        $attendance = DB::table('absensis')->where('nik_karyawan', 'EMP001')->first();

        $this->assertNull($status);
        $this->assertSame('2026-05-10 08:00:00', $attendance->jam_masuk);
        $this->assertNull($attendance->status_presensi);
    }

    private function makeHrUser(): User
    {
        return User::query()->firstOrCreate(
            ['id' => 'hr-user'],
            [
                'name' => 'HR User',
                'email' => 'hr@example.test',
                'password' => bcrypt('password'),
                'role_id' => 1,
            ]
        );
    }

    private function seedBaseData(): void
    {
        DB::table('roles')->insert([
            'id' => 1,
            'permission_role' => 'HR',
            'description' => 'HR',
            'menu_permissions' => json_encode(['attendance_period_lock']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employees')->insert([
            'nik' => 'EMP001',
            'nama_karyawan' => 'Employee Test',
            'area_kerja' => 'VDNI',
            'status_resign' => 'AKTIF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('permission_role');
            $table->string('description')->nullable();
            $table->longText('menu_permissions')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->unsignedInteger('role_id')->nullable();
            $table->string('nik_karyawan')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik')->primary();
            $table->string('nama_karyawan');
            $table->string('area_kerja')->nullable();
            $table->string('status_resign')->nullable();
            $table->unsignedInteger('work_pattern_id')->nullable();
            $table->date('work_pattern_start_date')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_period_locks', function (Blueprint $table) {
            $table->increments('id');
            $table->string('period_key', 7)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('locked');
            $table->string('closed_by', 36)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('close_note')->nullable();
            $table->string('reopened_by', 36)->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_note')->nullable();
            $table->longText('summary')->nullable();
            $table->timestamps();
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
            $table->timestamps();
        });

        Schema::create('presensi_verifications', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('presensi_id')->nullable();
            $table->string('nik_karyawan')->nullable();
            $table->date('tanggal');
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('cuti_izin', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nik_karyawan');
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_berakhir')->nullable();
            $table->string('tipe')->nullable();
            $table->unsignedTinyInteger('status_hod')->default(0);
            $table->unsignedTinyInteger('status_hrd')->default(0);
            $table->timestamps();
        });

        Schema::create('cuti_roster', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nik_karyawan');
            $table->date('tgl_mulai_cuti')->nullable();
            $table->date('tgl_mulai_cuti_berakhir')->nullable();
            $table->date('tgl_mulai_cuti_tahunan')->nullable();
            $table->date('tgl_mulai_cuti_tahunan_berakhir')->nullable();
            $table->date('tgl_mulai_off')->nullable();
            $table->date('tgl_mulai_off_berakhir')->nullable();
            $table->date('tgl_awal_kerja')->nullable();
            $table->date('tgl_akhir_kerja')->nullable();
            $table->unsignedTinyInteger('status_pengajuan')->default(0);
            $table->unsignedTinyInteger('status_pengajuan_hrd')->default(0);
            $table->timestamps();
        });

        Schema::create('periode_kerja_roster', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cuti_roster_id');
            $table->date('periode_awal')->nullable();
            $table->date('periode_akhir')->nullable();
            $table->unsignedTinyInteger('tipe_rencana')->default(0);
            $table->timestamps();
        });

        Schema::create('roster_off_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nik_karyawan');
            $table->date('tanggal_off');
            $table->unsignedTinyInteger('status_hod')->default(0);
            $table->unsignedTinyInteger('status_hrd')->default(0);
            $table->timestamps();
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nik_karyawan');
            $table->date('tanggal');
            $table->unsignedTinyInteger('status_hod')->default(0);
            $table->unsignedTinyInteger('status_hrd')->default(0);
            $table->timestamps();
        });

        Schema::create('overtime_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nik_karyawan');
            $table->date('overtime_date')->nullable();
            $table->string('employee_response_status')->default('PENDING');
            $table->timestamps();
        });

        Schema::create('employee_attendance_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('employee_id');
            $table->date('tanggal');
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('work_patterns', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_trails', function (Blueprint $table) {
            $table->increments('id');
            $table->string('event');
            $table->string('module')->nullable();
            $table->string('auditable_type')->nullable();
            $table->string('auditable_id')->nullable();
            $table->string('reference_table')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('employee_nik')->nullable();
            $table->string('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_role')->nullable();
            $table->longText('old_values')->nullable();
            $table->longText('new_values')->nullable();
            $table->longText('metadata')->nullable();
            $table->text('note')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }
}
