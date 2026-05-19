<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PresensiDedupeCommandTest extends TestCase
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

    public function test_dedupe_presensi_merges_safe_duplicate_rows_and_moves_references(): void
    {
        DB::table('absensis')->insert([
            [
                'id' => 1,
                'nik_karyawan' => 'EMP001',
                'tanggal' => '2026-05-19',
                'jam_masuk' => '2026-05-19 08:00:00',
                'jam_istirahat' => null,
                'created_at' => '2026-05-19 08:00:00',
                'updated_at' => '2026-05-19 08:00:00',
            ],
            [
                'id' => 2,
                'nik_karyawan' => 'EMP001',
                'tanggal' => '2026-05-19',
                'jam_masuk' => null,
                'jam_istirahat' => '2026-05-19 12:00:00',
                'created_at' => '2026-05-19 12:00:00',
                'updated_at' => '2026-05-19 12:00:00',
            ],
        ]);
        DB::table('presensi_verifications')->insert([
            ['id' => 1, 'presensi_id' => 1, 'nik_karyawan' => 'EMP001', 'tanggal' => '2026-05-19', 'attendance_type' => 'masuk', 'status' => 'verified'],
            ['id' => 2, 'presensi_id' => 2, 'nik_karyawan' => 'EMP001', 'tanggal' => '2026-05-19', 'attendance_type' => 'istirahat', 'status' => 'verified'],
        ]);
        DB::table('attendance_corrections')->insert([
            'id' => 1,
            'nik_karyawan' => 'EMP001',
            'presensi_id' => 2,
            'tanggal' => '2026-05-19',
            'reason' => 'Test correction',
        ]);

        $this->artisan('presensi:dedupe-employee-date')->assertExitCode(0);
        $this->assertSame(2, DB::table('absensis')->count());

        $exitCode = Artisan::call('presensi:dedupe-employee-date', ['--apply' => true]);
        $this->assertSame(0, $exitCode, Artisan::output());

        $this->assertSame(1, DB::table('absensis')->count());
        $this->assertDatabaseHas('absensis', [
            'id' => 1,
            'nik_karyawan' => 'EMP001',
            'tanggal' => '2026-05-19',
            'jam_masuk' => '2026-05-19 08:00:00',
            'jam_istirahat' => '2026-05-19 12:00:00',
        ]);
        $this->assertSame([1], DB::table('presensi_verifications')->pluck('presensi_id')->map(fn($id) => (int) $id)->unique()->values()->all());
        $this->assertSame(1, (int) DB::table('attendance_corrections')->value('presensi_id'));
        $this->assertDatabaseHas('presensi_deduplication_backups', [
            'kept_absensi_id' => 1,
            'deleted_absensi_id' => 2,
        ]);
    }

    public function test_dedupe_presensi_skips_conflicting_attendance_values(): void
    {
        DB::table('absensis')->insert([
            [
                'id' => 1,
                'nik_karyawan' => 'EMP001',
                'tanggal' => '2026-05-19',
                'jam_masuk' => '2026-05-19 08:00:00',
            ],
            [
                'id' => 2,
                'nik_karyawan' => 'EMP001',
                'tanggal' => '2026-05-19',
                'jam_masuk' => '2026-05-19 08:15:00',
            ],
        ]);

        $this->artisan('presensi:dedupe-employee-date --apply')->assertExitCode(2);

        $this->assertSame(2, DB::table('absensis')->count());
    }

    public function test_repair_zero_dates_infers_attendance_date_from_time_columns(): void
    {
        DB::table('absensis')->insert([
            [
                'id' => 1,
                'nik_karyawan' => 'EMP001',
                'tanggal' => '0000-00-00',
                'jam_masuk' => '2026-05-19 08:00:00',
            ],
            [
                'id' => 2,
                'nik_karyawan' => 'EMP001',
                'tanggal' => '0000-00-00',
                'jam_masuk' => '2026-05-20 08:10:00',
            ],
        ]);

        $this->artisan('presensi:dedupe-employee-date --repair-zero-dates --nik=EMP001 --date=0000-00-00')
            ->assertExitCode(0);
        $this->assertSame(2, DB::table('absensis')->where('tanggal', '0000-00-00')->count());

        $exitCode = Artisan::call('presensi:dedupe-employee-date', [
            '--repair-zero-dates' => true,
            '--apply' => true,
            '--nik' => 'EMP001',
            '--date' => '0000-00-00',
        ]);
        $this->assertSame(0, $exitCode, Artisan::output());

        $this->assertDatabaseHas('absensis', [
            'id' => 1,
            'tanggal' => '2026-05-19',
        ]);
        $this->assertDatabaseHas('absensis', [
            'id' => 2,
            'tanggal' => '2026-05-20',
        ]);
        $this->assertSame(2, DB::table('presensi_zero_date_repair_backups')->count());
    }

    private function createSchema(): void
    {
        Schema::create('absensis', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nik_karyawan', 100);
            $table->date('tanggal');
            $table->dateTime('jam_masuk')->nullable();
            $table->dateTime('jam_istirahat')->nullable();
            $table->dateTime('jam_kembali_istirahat')->nullable();
            $table->dateTime('jam_pulang')->nullable();
            $table->string('status_presensi', 100)->nullable();
            $table->string('status_absen', 64)->nullable();
            $table->boolean('face_verified')->default(false);
            $table->string('face_selfie_hash', 64)->nullable();
            $table->string('presensi_challenge_id', 80)->nullable();
            $table->timestamps();
        });

        Schema::create('presensi_verifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('presensi_id');
            $table->string('nik_karyawan', 100);
            $table->date('tanggal');
            $table->string('attendance_type', 32);
            $table->string('status', 64);
            $table->unique(['presensi_id', 'attendance_type'], 'presensi_verifications_presensi_type_unique');
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nik_karyawan', 100);
            $table->unsignedBigInteger('presensi_id')->nullable();
            $table->date('tanggal');
            $table->text('reason');
        });
    }
}
