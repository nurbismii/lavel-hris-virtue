<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\Presensi;
use App\Models\Role;
use App\Models\User;
use App\Services\AttendanceCorrection\AttendanceCorrectionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceCorrectionServiceTest extends TestCase
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
    }

    public function test_submit_creates_pending_correction_with_old_values(): void
    {
        $this->seedEmployee();
        Presensi::create([
            'nik_karyawan' => 'EMP001',
            'tanggal' => '2026-05-07',
            'jam_masuk' => '2026-05-07 08:00:00',
            'jam_pulang' => '2026-05-07 17:00:00',
        ]);

        $result = app(AttendanceCorrectionService::class)->submit($this->makeUser(), [
            'tanggal' => '2026-05-07',
            'jam_pulang' => '18:30',
            'reason' => 'Jam pulang aktual belum terekam karena perangkat bermasalah.',
        ]);

        $this->assertTrue($result['status']);
        $this->assertDatabaseHas('attendance_corrections', [
            'nik_karyawan' => 'EMP001',
            'tanggal' => '2026-05-07',
            'status_hod' => AttendanceCorrection::STATUS_PENDING,
            'status_hrd' => AttendanceCorrection::STATUS_PENDING,
        ]);

        $correction = AttendanceCorrection::first();
        $this->assertSame('2026-05-07 17:00:00', $correction->old_values['jam_pulang']);
        $this->assertSame('18:30', $correction->requested_jam_pulang->format('H:i'));
    }

    public function test_hr_approval_applies_correction_after_hod_approval(): void
    {
        $this->seedEmployee();
        Presensi::create([
            'nik_karyawan' => 'EMP001',
            'tanggal' => '2026-05-07',
            'jam_masuk' => '2026-05-07 08:00:00',
            'jam_pulang' => '2026-05-07 17:00:00',
        ]);

        $service = app(AttendanceCorrectionService::class);
        $submission = $service->submit($this->makeUser(), [
            'tanggal' => '2026-05-07',
            'jam_pulang' => '18:30',
            'status_presensi' => '__clear__',
            'reason' => 'Jam pulang aktual belum terekam karena perangkat bermasalah.',
        ]);

        $correction = $submission['correction'];
        $service->processHod($correction, $this->makeApprover('hod-user', 'HOD User', 'HOD'), AttendanceCorrection::STATUS_APPROVED);
        $service->processHrd($correction->fresh(), $this->makeApprover('hr-user', 'HR User', 'HR'), AttendanceCorrection::STATUS_APPROVED);

        $presensi = Presensi::where('nik_karyawan', 'EMP001')->whereDate('tanggal', '2026-05-07')->first();

        $this->assertSame('18:30', $presensi->jam_pulang->format('H:i'));
        $this->assertNull($presensi->status_presensi);
        $this->assertDatabaseHas('attendance_corrections', [
            'id' => $correction->id,
            'status_hod' => AttendanceCorrection::STATUS_APPROVED,
            'status_hrd' => AttendanceCorrection::STATUS_APPROVED,
        ]);
        $this->assertDatabaseHas('audit_trails', [
            'event' => 'attendance_correction.applied',
            'module' => 'attendance_correction',
            'employee_nik' => 'EMP001',
        ]);
    }

    public function test_duplicate_active_correction_for_same_date_is_rejected(): void
    {
        $this->seedEmployee();
        $user = $this->makeUser();
        $service = app(AttendanceCorrectionService::class);

        $service->submit($user, [
            'tanggal' => '2026-05-07',
            'jam_masuk' => '08:15',
            'reason' => 'Jam masuk perlu disesuaikan berdasarkan bukti gate.',
        ]);

        $second = $service->submit($user, [
            'tanggal' => '2026-05-07',
            'jam_pulang' => '18:00',
            'reason' => 'Jam pulang perlu disesuaikan berdasarkan bukti gate.',
        ]);

        $this->assertFalse($second['status']);
        $this->assertSame(1, AttendanceCorrection::count());
    }

    private function createSchema(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik')->primary();
            $table->string('nama_karyawan');
            $table->string('area_kerja')->nullable();
            $table->string('status_resign')->nullable();
            $table->unsignedBigInteger('departemen_id')->nullable();
            $table->unsignedBigInteger('divisi_id')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('nik_karyawan')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->timestamps();
        });

        Schema::create('absensis', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nik_karyawan', 32);
            $table->date('tanggal');
            $table->dateTime('jam_masuk')->nullable();
            $table->dateTime('jam_istirahat')->nullable();
            $table->dateTime('jam_kembali_istirahat')->nullable();
            $table->dateTime('jam_pulang')->nullable();
            $table->string('status_presensi', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nik_karyawan', 32);
            $table->unsignedBigInteger('presensi_id')->nullable();
            $table->date('tanggal');
            $table->dateTime('requested_jam_masuk')->nullable();
            $table->dateTime('requested_jam_istirahat')->nullable();
            $table->dateTime('requested_jam_kembali_istirahat')->nullable();
            $table->dateTime('requested_jam_pulang')->nullable();
            $table->boolean('change_status_presensi')->default(false);
            $table->string('requested_status_presensi', 100)->nullable();
            $table->longText('old_values')->nullable();
            $table->longText('applied_values')->nullable();
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->unsignedTinyInteger('status_hod')->default(0);
            $table->string('hod_processed_by', 36)->nullable();
            $table->timestamp('hod_processed_at')->nullable();
            $table->string('hod_rejection_reason', 500)->nullable();
            $table->unsignedTinyInteger('status_hrd')->default(0);
            $table->string('hrd_processed_by', 36)->nullable();
            $table->timestamp('hrd_processed_at')->nullable();
            $table->string('hrd_rejection_reason', 500)->nullable();
            $table->string('applied_by', 36)->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->string('created_by', 36)->nullable();
            $table->timestamps();
        });

        Schema::create('audit_trails', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event', 80);
            $table->string('module', 80);
            $table->string('auditable_type', 120)->nullable();
            $table->string('auditable_id', 64)->nullable();
            $table->string('reference_table', 80)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->string('employee_nik', 32)->nullable();
            $table->string('actor_id', 36)->nullable();
            $table->string('actor_name', 150)->nullable();
            $table->string('actor_role', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->longText('old_values')->nullable();
            $table->longText('new_values')->nullable();
            $table->longText('metadata')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });
    }

    private function seedEmployee(): Employee
    {
        return Employee::create([
            'nik' => 'EMP001',
            'nama_karyawan' => 'Karyawan Test',
            'area_kerja' => 'VDNI',
            'status_resign' => 'AKTIF',
        ]);
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->id = 'employee-user';
        $user->name = 'Employee User';
        $user->email = 'employee@example.test';
        $user->nik_karyawan = 'EMP001';
        $user->setRelation('role', new Role(['permission_role' => 'Staff']));

        return $user;
    }

    private function makeApprover(string $id, string $name, string $role): User
    {
        $user = new User();
        $user->id = $id;
        $user->name = $name;
        $user->email = $id . '@example.test';
        $user->setRelation('role', new Role(['permission_role' => $role]));

        return $user;
    }
}
