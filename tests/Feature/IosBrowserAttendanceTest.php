<?php

namespace Tests\Feature;

use App\Http\Controllers\User\PresensiController;
use App\Http\Middleware\RequireNativeGpsProof;
use App\Models\Employee;
use App\Models\LokasiAbsen;
use App\Models\User;
use App\Services\Presensi\AttendanceDateResolverService;
use App\Services\Presensi\AttendanceLocationResolverService;
use App\Services\Presensi\AttendanceSecurityService;
use App\Services\Presensi\AttendanceStatusService;
use App\Services\Presensi\OvertimeOrderService;
use App\Services\Presensi\ShiftAssignmentService;
use App\Services\Presensi\WorkScheduleService;
use App\Support\MobileApp;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class IosBrowserAttendanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'hris.mobile_app.required' => true,
            'hris.mobile_app.ios_required' => false,
            'hris.mobile_app.ios_browser_attendance' => true,
        ]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 'ios-test', 'nik_karyawan' => 'TEST-IOS', 'email_verified_at' => now()]);
        $user->setRelation('role', null);
        $user->shouldReceive('hasAnyRole')->andReturn(true);
        $user->shouldReceive('hasMenuAccess')->andReturn(true);
        $this->actingAs($user);
    }

    public function test_ios_can_reach_all_attendance_actions_but_still_needs_camera_and_challenge(): void
    {
        foreach (['masuk', 'istirahat', 'kembali', 'pulang'] as $type) {
            $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)')
                ->postJson('/absen/' . $type, ['lat_user' => -4, 'long_user' => 122, 'accuracy' => 10])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['selfie_capture_data', 'attendance_challenge_token', 'face_liveness_evidence'])
                ->assertJsonMissingValidationErrors(['gps_proof', 'lat_user', 'long_user', 'accuracy']);
        }
        foreach (['iPad', 'Macintosh; Intel Mac OS X 10_15'] as $agent) {
            $this->withHeader('User-Agent', $agent)->postJson('/absen/masuk', [
                'ios_browser' => '1', 'lat_user' => 91, 'long_user' => 181, 'accuracy' => 0, 'speed' => 51,
            ])->assertUnprocessable()->assertJsonMissingValidationErrors('gps_proof')
                ->assertJsonValidationErrors(['lat_user', 'long_user', 'accuracy', 'speed', 'selfie_capture_data']);
        }
    }

    public function test_ios_receives_a_challenge_and_browser_ui_while_desktop_and_disabled_policy_stay_locked(): void
    {
        Schema::create('absensis', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nik_karyawan');
            $table->date('tanggal');
        });
        $employee = new Employee(['nik' => 'TEST-IOS', 'face_reference_path' => 'face-reference/synthetic.jpg']);
        $employee->setRelation('workPattern', null);
        auth()->user()->setRelation('employee', $employee);
        $this->mock(AttendanceDateResolverService::class)->shouldReceive('resolve')->andReturn([
            'date' => now()->toDateString(), 'shift' => null, 'schedule_source' => null, 'schedule_data' => [], 'is_cross_day' => false,
        ]);
        $this->mock(AttendanceLocationResolverService::class)->shouldReceive('resolveAllForEmployee')->andReturn(collect([
            new LokasiAbsen(['id' => 1, 'lat' => -4, 'long' => 122, 'radius' => 100]),
        ]));
        $this->mock(AttendanceStatusService::class)->shouldReceive('syncStatusForDate')->andReturnNull();
        $overtime = $this->mock(OvertimeOrderService::class);
        $overtime->shouldReceive('getAcceptedOrderForDate')->andReturnNull();
        $overtime->shouldReceive('buildAcceptedAlphaVirtualRows')->andReturn([]);
        $this->mock(WorkScheduleService::class)->shouldReceive('buildVirtualOffRows')->andReturn([]);
        $this->mock(ShiftAssignmentService::class)->shouldReceive('getAssignmentsForEmployees')->andReturn(collect());
        $blade = str_replace("@extends('layouts.app')", '', file_get_contents(resource_path('views/user/presensi/index.blade.php')))
            . "\n@yield('content')\n@stack('scripts')";

        foreach ([['iPhone', true, true], ['Macintosh', true, true], ['Windows NT 10.0', true, false], ['iPhone', false, false]] as [$agent, $enabled, $allowed]) {
            config(['hris.mobile_app.ios_browser_attendance' => $enabled]);
            $request = Request::create('/presensi', 'GET', ['ios_browser' => '1'], [], [], ['HTTP_USER_AGENT' => $agent]);
            $this->app->instance('request', $request);
            $data = app(PresensiController::class)->index()->getData();
            $this->assertSame($allowed, $data['supportsIosBrowserGps']);
            $this->assertSame($allowed, $data['attendanceChallenge'] !== null);
            $data['errors'] = new \Illuminate\Support\ViewErrorBag();
            $html = Blade::render($blade, $data, true);
            if ($allowed) {
                $this->assertStringContainsString('Presensi iPhone/iPad melalui browser tersedia sementara.', $html);
                $this->assertStringContainsString('const supportsIosBrowserGps = true;', $html);
                $this->assertStringContainsString('name="ios_browser" value="1"', $html);
                $this->assertStringNotContainsString('Untuk mencatat presensi, gunakan V-People Android', $html);
            } else {
                $this->assertStringContainsString('Untuk mencatat presensi, gunakan V-People Android', $html);
                $this->assertStringContainsString('const supportsIosBrowserGps = false;', $html);
            }
        }
    }

    public function test_ios_browser_gps_logs_validate_coordinates_accuracy_and_speed(): void
    {
        foreach (['lat' => 91, 'long' => -181, 'accuracy' => 0, 'speed' => 51] as $field => $invalid) {
            $payload = array_replace(['lat' => -4, 'long' => 122, 'accuracy' => 10, 'speed' => 0], [$field => $invalid]);
            $this->withHeader('User-Agent', 'iPhone')->postJson('/api/gps-log', $payload)
                ->assertUnprocessable()->assertJsonValidationErrors($field)->assertJsonMissingValidationErrors('gps_proof');
        }
    }

    public function test_disabling_the_exception_or_requiring_the_ios_app_restores_signed_gps_requirement(): void
    {
        config(['hris.mobile_app.required' => false]);
        foreach (['ios_browser_attendance' => false, 'ios_required' => true] as $setting => $value) {
            config(['hris.mobile_app.ios_browser_attendance' => true, 'hris.mobile_app.ios_required' => false]);
            config(['hris.mobile_app.' . $setting => $value]);
            foreach (['/api/gps-log', '/absen/masuk'] as $url) {
                $this->withHeader('User-Agent', 'iPhone')->postJson($url, [])
                    ->assertUnprocessable()->assertJsonValidationErrors('gps_proof');
            }
        }
    }

    public function test_android_desktop_and_native_ios_do_not_gain_unsigned_gps_from_the_browser_hint(): void
    {
        config(['hris.mobile_app.required' => false]);
        foreach (['Android', 'Android iPhone', 'Android VPEOPLE_APP/1.0.10', 'Windows NT 10.0', 'Linux', 'iPhone VPEOPLE_APP/1.0.0'] as $agent) {
            foreach (['/api/gps-log', '/absen/masuk'] as $url) {
                $this->withHeader('User-Agent', $agent)->postJson($url, ['ios_browser' => '1', 'mocked' => false])
                    ->assertUnprocessable()->assertJsonValidationErrors('gps_proof');
            }
        }
        $request = Request::create('/presensi', 'GET', [], [], [], ['HTTP_USER_AGENT' => 'Macintosh']);
        $this->assertFalse(MobileApp::allowsIosBrowserAttendance($request));
        $request->merge(['ios_browser' => '1']);
        $this->assertTrue(MobileApp::allowsIosBrowserAttendance($request));
    }

    public function test_browser_gps_method_is_recorded_by_the_server_in_existing_audit_metadata(): void
    {
        $request = Request::create('/absen/masuk', 'POST', ['gps_verification_method' => 'android_hardware'], [], [], ['HTTP_USER_AGENT' => 'iPhone']);
        (new RequireNativeGpsProof())->handle($request, fn($request) => null);
        $meta = json_decode(app(AttendanceSecurityService::class)->buildStoredMeta($request,
            ['id' => 'test-challenge', 'issued_at' => now()->toIso8601String(), 'expires_at' => now()->addMinutes(5)->toIso8601String()],
            ['meta' => []], ['hash' => 'test', 'mime_type' => 'image/jpeg', 'size' => 20000, 'width' => 480, 'height' => 640]
        ), true);
        $this->assertSame('ios_browser', $meta['server_validation']['gps_verification_method']);
    }

    public function test_ios_exception_keeps_authentication(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withHeader('User-Agent', 'iPhone')->postJson('/api/gps-log', [])->assertUnauthorized();
    }
}
