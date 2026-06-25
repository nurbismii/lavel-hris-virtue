<?php

namespace Tests\Feature;

use App\Services\Presensi\AttendanceStatusService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceStatusServiceTest extends TestCase
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
        DB::table('employees')->insert([
            'nik' => 'EMP001',
            'nama_karyawan' => 'Employee Test',
        ]);
    }

    public function test_hrd_pending_cuti_does_not_clear_existing_attendance_times(): void
    {
        DB::table('absensis')->insert([
            'nik_karyawan' => 'EMP001',
            'tanggal' => '2026-05-10',
            'jam_masuk' => '2026-05-10 08:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('cuti_izin')->insert([
            'nik_karyawan' => 'EMP001',
            'tanggal_mulai' => '2026-05-10',
            'tanggal_berakhir' => '2026-05-10',
            'tipe' => 'CUTI',
            'status_hod' => 1,
            'status_hrd' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $status = app(AttendanceStatusService::class)->syncStatusForDate('EMP001', '2026-05-10');
        $attendance = DB::table('absensis')->where('nik_karyawan', 'EMP001')->first();

        $this->assertNull($status);
        $this->assertSame('2026-05-10 08:00:00', $attendance->jam_masuk);
        $this->assertNull($attendance->status_presensi);
    }

    public function test_hrd_approved_cuti_sets_attendance_status_and_clears_times(): void
    {
        DB::table('absensis')->insert([
            'nik_karyawan' => 'EMP001',
            'tanggal' => '2026-05-10',
            'jam_masuk' => '2026-05-10 08:00:00',
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

        $this->assertSame(AttendanceStatusService::STATUS_CUTI_TAHUNAN, $status);
        $this->assertNull($attendance->jam_masuk);
        $this->assertSame(AttendanceStatusService::STATUS_CUTI_TAHUNAN, $attendance->status_presensi);
    }

    public function test_hrd_pending_roster_does_not_clear_existing_attendance_times(): void
    {
        DB::table('absensis')->insert([
            'nik_karyawan' => 'EMP001',
            'tanggal' => '2026-05-10',
            'jam_masuk' => '2026-05-10 08:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('cuti_roster')->insert([
            'id' => 1,
            'nik_karyawan' => 'EMP001',
            'tgl_mulai_cuti' => '2026-05-10',
            'tgl_mulai_cuti_berakhir' => '2026-05-10',
            'status_pengajuan' => 1,
            'status_pengajuan_hrd' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('periode_kerja_roster')->insert([
            'cuti_roster_id' => 1,
            'tipe_rencana' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $status = app(AttendanceStatusService::class)->syncStatusForDate('EMP001', '2026-05-10');
        $attendance = DB::table('absensis')->where('nik_karyawan', 'EMP001')->first();

        $this->assertNull($status);
        $this->assertSame('2026-05-10 08:00:00', $attendance->jam_masuk);
        $this->assertNull($attendance->status_presensi);
    }

    private function createSchema(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik')->primary();
            $table->string('nama_karyawan');
            $table->unsignedInteger('work_pattern_id')->nullable();
            $table->date('work_pattern_start_date')->nullable();
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

        Schema::create('roster_off_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nik_karyawan');
            $table->date('tanggal_off');
            $table->unsignedTinyInteger('status_hod')->default(0);
            $table->unsignedTinyInteger('status_hrd')->default(0);
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
    }
}
