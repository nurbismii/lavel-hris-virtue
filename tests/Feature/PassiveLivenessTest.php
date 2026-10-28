<?php

namespace Tests\Feature;

use App\Models\User;
use App\Http\Controllers\User\PresensiController;
use App\Http\Requests\Presensi\PresensiRequest;
use App\Services\Presensi\AttendanceSecurityService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PassiveLivenessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        Route::post('/_test/presensi-capture', function (PresensiRequest $request, AttendanceSecurityService $security) {
            $challenge = $security->consumeChallenge($request, $request->user(), now()->toDateString(), 'masuk');
            return response()->json($security->validateFacePayload($request, $challenge)['liveness']);
        });
    }

    private function challenge(string $mode): array
    {
        config()->set('services.presensi_face.liveness_mode', $mode);
        $user = new User(['id' => 'passive-test-user', 'nik_karyawan' => 'TEST-001']);
        return app(AttendanceSecurityService::class)->issueChallenge($user, Request::create('/presensi'), now()->toDateString(), 'masuk');
    }

    private function payload(array $challenge): array
    {
        $passive = $challenge['liveness_action'] === 'passive';
        return [
            'face_verified' => '1', 'face_distance' => '0.3', 'face_detection_count' => 1,
            'face_liveness_passed' => $passive ? '0' : '1', 'face_liveness_score' => $passive ? null : 1,
            'screen_spoof_score' => 0,
            'presensi_challenge_id' => $challenge['id'], 'presensi_challenge_action' => $challenge['liveness_action'],
            'face_liveness_type' => $challenge['liveness_action'],
            'face_verification_meta' => json_encode([
                'source' => 'live-camera', 'challenge_id' => $challenge['id'], 'distance' => 0.3,
                'detection_score' => 0.99, 'roll_angle' => 0, 'frame_state' => 'green',
                'challenge_elapsed_ms' => 3000, 'verified_at_client' => now()->toIso8601String(),
                'client_liveness' => ['passed' => !$passive, 'capture_ready' => true,
                    'challenge_id' => $challenge['id'], 'challenge_action' => $challenge['liveness_action']],
            ]),
            'face_liveness_evidence' => json_encode(['frames' => array_map(function ($label, $index) {
                return ['label' => $label, 'captured_at_ms' => 1 + $index * 1000, 'image' => 'data:image/jpeg;base64,AA=='];
            }, $challenge['liveness_required_frames'], array_keys($challenge['liveness_required_frames']))]),
        ];
    }

    private function submission(array $challenge): array
    {
        return array_merge($this->payload($challenge), [
            'lat_user' => -3.9, 'long_user' => 122.5, 'accuracy' => 10,
            'device_info' => 'Test camera', 'selfie_capture' => UploadedFile::fake()->image('selfie.jpg', 480, 640),
            'attendance_challenge_token' => $challenge['token'], 'attendance_challenge_id' => $challenge['id'],
        ]);
    }

    public function test_passive_http_submission_accepts_an_empty_or_missing_browser_liveness_score(): void
    {
        $this->actingAs(new User(['id' => 'passive-test-user', 'nik_karyawan' => 'TEST-001']));
        foreach ([null, '', 'missing'] as $score) {
            $payload = $this->submission($this->challenge('passive'));
            $payload['face_liveness_score'] = $score;
            if ($score === 'missing') {
                unset($payload['face_liveness_score']);
            }
            $this->postJson('/_test/presensi-capture', $payload)->assertOk()
                ->assertJson(['type' => 'passive', 'passed' => false, 'capture_ready' => true]);
        }
    }

    public function test_active_http_submission_keeps_the_required_numeric_bounded_liveness_score(): void
    {
        $this->actingAs(new User(['id' => 'passive-test-user', 'nik_karyawan' => 'TEST-001']));
        foreach ([null, '', 'invalid', -0.1, 2.1] as $score) {
            $payload = $this->submission($this->challenge('active'));
            $payload['face_liveness_score'] = $score;
            $this->postJson('/_test/presensi-capture', $payload)->assertStatus(422)
                ->assertJsonValidationErrors('face_liveness_score');
        }
        $this->postJson('/_test/presensi-capture', $this->submission($this->challenge('active')))
            ->assertOk()->assertJson(['type' => 'turn_left_right', 'passed' => true]);
    }

    public function test_http_submission_rejects_unknown_actions_and_forged_passive_mode(): void
    {
        $this->actingAs(new User(['id' => 'passive-test-user', 'nik_karyawan' => 'TEST-001']));
        foreach (['presensi_challenge_action', 'face_liveness_type'] as $field) {
            $payload = $this->submission($this->challenge('passive'));
            $payload[$field] = 'unknown';
            $this->postJson('/_test/presensi-capture', $payload)->assertStatus(422)->assertJsonValidationErrors($field);
        }
        $challenge = $this->challenge('active');
        $challenge['liveness_action'] = 'passive';
        $challenge['liveness_required_frames'] = ['passive_start', 'passive_middle', 'passive_end'];
        $this->postJson('/_test/presensi-capture', $this->submission($challenge))
            ->assertStatus(422)->assertJsonValidationErrors('presensi_security');
    }

    public function test_passive_capture_needs_no_turn_frames_and_is_not_a_server_liveness_success(): void
    {
        $challenge = $this->challenge('passive');
        $this->assertSame(['passive_start', 'passive_middle', 'passive_end'], $challenge['liveness_required_frames']);
        $this->assertSame(2000, $challenge['capture_duration_ms']);
        config()->set('services.presensi_face.liveness_mode', 'active');
        $result = app(AttendanceSecurityService::class)->validateFacePayload(Request::create('/absen/masuk', 'POST', $this->payload($challenge)), $challenge);
        $this->assertTrue($result['liveness']['capture_ready']);
        $this->assertFalse($result['liveness']['passed']);
        $this->assertSame('passive', $result['liveness']['type']);
    }

    public function test_existing_active_challenge_still_requires_turn_evidence(): void
    {
        $challenge = $this->challenge('active');
        $this->assertSame(['center', 'turn_left', 'turn_right'], $challenge['liveness_required_frames']);
        $result = app(AttendanceSecurityService::class)->validateFacePayload(Request::create('/', 'POST', $this->payload($challenge)), $challenge);
        $this->assertTrue($result['liveness']['passed']);
        $passive = $this->payload($this->challenge('passive'));
        $this->expectException(ValidationException::class);
        app(AttendanceSecurityService::class)->validateFacePayload(Request::create('/', 'POST', $passive), $challenge);
    }

    public function test_incomplete_short_reordered_and_duplicate_capture_evidence_is_rejected(): void
    {
        $challenge = $this->challenge('passive');
        $valid = $this->payload($challenge);
        $frames = json_decode($valid['face_liveness_evidence'], true)['frames'];
        $invalid = [
            array_slice($frames, 0, 2),
            [$frames[0], $frames[1], $frames[1]],
            [$frames[0], array_merge($frames[1], ['captured_at_ms' => 2]), array_merge($frames[2], ['captured_at_ms' => 3])],
            [$frames[0], array_merge($frames[1], ['captured_at_ms' => 3000]), $frames[2]],
        ];
        foreach ($invalid as $items) {
            $payload = array_merge($valid, ['face_liveness_evidence' => json_encode(['frames' => $items])]);
            try {
                app(AttendanceSecurityService::class)->validateFacePayload(Request::create('/', 'POST', $payload), $challenge);
                $this->fail('Invalid passive capture must not be accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('presensi_security', $exception->errors());
            }
        }
    }

    public function test_passive_frames_are_stored_privately_and_partial_files_are_cleaned_on_invalid_image(): void
    {
        $disk = Storage::fake('local');
        $challenge = $this->challenge('passive');
        $image = UploadedFile::fake()->image('frame.jpg', 480, 640);
        $frames = array_map(function ($label) use ($image) {
            return ['label' => $label, 'image' => 'data:image/jpeg;base64,' . base64_encode(file_get_contents($image->getRealPath()))];
        }, $challenge['liveness_required_frames']);
        $store = new \ReflectionMethod(PresensiController::class, 'storeLivenessEvidenceFrames');
        $store->setAccessible(true);
        $request = Request::create('/', 'POST', ['face_liveness_evidence' => json_encode(['frames' => $frames])]);
        $paths = $store->invoke(app(PresensiController::class), $request, 'TEST-001', 'masuk', $challenge);
        $this->assertSame($challenge['liveness_required_frames'], array_keys($paths));
        foreach ($paths as $path) {
            $this->assertStringStartsWith('presensi-liveness/TEST-001/', $path);
            $disk->assertExists($path);
        }
        $disk->delete(array_values($paths));
        $frames[2]['image'] = 'data:image/jpeg;base64,AA==';
        $request->merge(['face_liveness_evidence' => json_encode(['frames' => $frames])]);
        try {
            $store->invoke(app(PresensiController::class), $request, 'TEST-001', 'masuk', $challenge);
            $this->fail('Invalid JPEG data must not be stored.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('presensi', $exception->errors());
            $this->assertSame([], $disk->allFiles());
        }
    }
}
