<?php

namespace Tests\Feature;

use App\Models\User;
use App\Http\Controllers\User\PresensiController;
use App\Services\Presensi\AttendanceSecurityService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PassiveLivenessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
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
