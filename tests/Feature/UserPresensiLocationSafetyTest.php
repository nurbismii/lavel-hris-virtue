<?php

namespace Tests\Feature;

use App\Http\Controllers\User\PresensiController;
use App\Models\Employee;
use App\Models\User;
use App\Services\Presensi\AttendanceSecurityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserPresensiLocationSafetyTest extends TestCase
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

    public function test_gps_log_rejects_missing_attendance_location(): void
    {
        $response = $this->sendGpsLog($this->makeUser());

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Lokasi presensi untuk divisi Anda belum diatur.', $this->jsonPayload($response)['message']);
        $this->assertSame(0, DB::table('log_presensi')->count());
    }

    public function test_gps_log_rejects_incomplete_attendance_location(): void
    {
        DB::table('lokasi_absens')->insert([
            'divisi_id' => 10,
            'lat' => '',
            'long' => '122.5123',
            'radius' => '100',
        ]);

        $response = $this->sendGpsLog($this->makeUser());

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('belum lengkap', $this->jsonPayload($response)['message']);
        $this->assertSame(0, DB::table('log_presensi')->count());
    }

    public function test_gps_log_rejects_invalid_attendance_location_radius(): void
    {
        DB::table('lokasi_absens')->insert([
            'divisi_id' => 10,
            'lat' => '-3.9951',
            'long' => '122.5123',
            'radius' => '0',
        ]);

        $response = $this->sendGpsLog($this->makeUser());

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('belum lengkap', $this->jsonPayload($response)['message']);
        $this->assertSame(0, DB::table('log_presensi')->count());
    }

    public function test_gps_log_accepts_complete_attendance_location(): void
    {
        DB::table('lokasi_absens')->insert([
            'divisi_id' => 10,
            'lat' => '-3.9951',
            'long' => '122.5123',
            'radius' => '100',
        ]);

        $response = $this->sendGpsLog($this->makeUser());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $this->jsonPayload($response)['status']);
        $this->assertSame(1, DB::table('log_presensi')->count());
    }

    public function test_gps_log_uses_employee_location_assignment_before_division_default(): void
    {
        DB::table('lokasi_absens')->insert([
            [
                'id' => 1,
                'divisi_id' => 10,
                'lat' => '0',
                'long' => '0',
                'radius' => '100',
            ],
            [
                'id' => 2,
                'divisi_id' => 20,
                'lat' => '-3.9951',
                'long' => '122.5123',
                'radius' => '100',
            ],
        ]);

        DB::table('employee_attendance_location_assignments')->insert([
            'employee_nik' => 'EMP001',
            'lokasi_absen_id' => 2,
            'effective_from' => now()->subDay()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->sendGpsLog($this->makeUser());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $this->jsonPayload($response)['status']);
        $this->assertSame(1, DB::table('log_presensi')->count());
    }

    private function createSchema(): void
    {
        Schema::create('lokasi_absens', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('divisi_id')->nullable();
            $table->string('lat')->nullable();
            $table->string('long')->nullable();
            $table->string('radius')->nullable();
            $table->timestamps();
        });

        Schema::create('log_presensi', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nik_karyawan')->nullable();
            $table->date('tanggal')->nullable();
            $table->string('lat')->nullable();
            $table->string('long')->nullable();
            $table->string('accuracy')->nullable();
            $table->string('speed')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_attendance_location_assignments', function (Blueprint $table) {
            $table->increments('id');
            $table->string('employee_nik')->nullable();
            $table->unsignedInteger('lokasi_absen_id')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamps();
        });
    }

    private function sendGpsLog(User $user, array $payload = [])
    {
        $request = Request::create('/api/gps-log', 'POST', array_merge([
            'lat' => '-3.9950',
            'long' => '122.5120',
            'accuracy' => 20,
            'speed' => 0,
        ], $payload));
        $request->setUserResolver(fn() => $user);

        $this->be($user);
        $this->app->instance('request', $request);

        return app(PresensiController::class)->logGps($request, app(AttendanceSecurityService::class));
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->id = 'user-emp001';
        $user->name = 'Employee User';
        $user->email = 'employee@example.test';
        $user->nik_karyawan = 'EMP001';
        $user->setRelation('employee', new Employee([
            'nik' => 'EMP001',
            'divisi_id' => 10,
        ]));

        return $user;
    }

    private function jsonPayload($response): array
    {
        return json_decode($response->getContent(), true) ?: [];
    }
}
