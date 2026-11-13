<?php

namespace Tests\Unit;

use App\Models\Presensi;
use App\Services\Presensi\ServerSideFaceVerificationService;
use Tests\TestCase;

class FaceProviderFailureTest extends TestCase
{
    public function test_active_liveness_cannot_override_a_negative_or_weak_identity_result(): void
    {
        config()->set('services.presensi_face.min_confidence', 0.78);
        config()->set('services.presensi_face.min_liveness_score', 0.78);
        config()->set('services.presensi_face.require_active_liveness', true);
        $service = app(ServerSideFaceVerificationService::class);
        $normalize = new \ReflectionMethod($service, 'normalizeProviderPayload');
        $decide = new \ReflectionMethod($service, 'buildDecision');
        $valid = [
            'status' => 'verified', 'verified' => true, 'face_matched' => true,
            'active_liveness_passed' => true, 'screen_attack_detected' => false,
            'confidence' => 0.99, 'liveness_score' => 0.95, 'distance' => 0.2, 'threshold' => 0.5,
        ];
        $this->assertTrue($decide->invoke($service, [], $normalize->invoke($service, $valid, 200))['passed']);
        foreach ([
            ['face_matched' => false], ['face_matched' => 'false'], ['confidence' => 0.2],
            ['confidence' => null], ['confidence' => 999], ['distance' => 0.8],
            ['active_liveness_passed' => 'false'], ['liveness_score' => null], ['liveness_score' => 0.3],
            ['status' => 'rejected'], ['screen_attack_detected' => true],
        ] as $failure) {
            $provider = $normalize->invoke($service, array_merge($valid, $failure), 200);
            $this->assertFalse($decide->invoke($service, [], $provider)['passed'], json_encode($failure));
        }
        $percent = array_merge($valid, ['confidence' => 99]);
        $this->assertTrue($decide->invoke($service, [], $normalize->invoke($service, $percent, 200))['passed']);
    }

    public function test_screen_attack_cannot_be_marked_verified_or_hidden_by_a_review_status(): void
    {
        $service = app(ServerSideFaceVerificationService::class);
        $decide = new \ReflectionMethod($service, 'buildDecision');
        foreach (['verified', 'pending_review'] as $status) {
            $result = $decide->invoke($service, [], [
                'status' => $status, 'face_matched' => true, 'liveness_passed' => true,
                'screen_attack_detected' => true,
            ]);
            $this->assertFalse($result['passed']);
            $this->assertSame(Presensi::STATUS_ABSEN_REJECTED, $result['status']);
        }
    }

    public function test_provider_outages_follow_fail_open_and_fail_closed_configuration(): void
    {
        $service = app(ServerSideFaceVerificationService::class);
        $normalize = new \ReflectionMethod($service, 'normalizeProviderPayload');
        $normalize->setAccessible(true);
        $decide = new \ReflectionMethod($service, 'buildDecision');
        $decide->setAccessible(true);
        foreach ([401, 403, 429, 500, 503] as $status) {
            $provider = $normalize->invoke($service, ['status' => 'error', 'message' => 'Provider unavailable'], $status);
            $this->assertNull($provider);
            foreach ([false, true] as $failClosed) {
                config()->set('services.presensi_face.fail_closed', $failClosed);
                $result = $decide->invoke($service, [], $provider);
                $this->assertFalse($result['passed']);
                $this->assertSame($failClosed ? Presensi::STATUS_ABSEN_REJECTED : Presensi::STATUS_ABSEN_PENDING_REVIEW, $result['status']);
                $this->assertNotEmpty($result['message']);
            }
        }
        $provider = $normalize->invoke($service, ['status' => 'rejected', 'face_matched' => false, 'screen_attack_detected' => true], 200);
        config()->set('services.presensi_face.fail_closed', false);
        $this->assertSame(Presensi::STATUS_ABSEN_REJECTED, $decide->invoke($service, [], $provider)['status']);
    }

    public function test_passive_mode_requires_explicit_complete_results_bound_to_the_capture_session(): void
    {
        $service = app(ServerSideFaceVerificationService::class);
        $normalize = new \ReflectionMethod($service, 'normalizeProviderPayload');
        $normalize->setAccessible(true);
        $decide = new \ReflectionMethod($service, 'buildDecision');
        $decide->setAccessible(true);
        $valid = [
            'verification_contract' => 'passive-v1', 'liveness_mode' => 'passive',
            'presensi_challenge_id' => 'capture-1', 'passive_liveness_passed' => true,
            'face_matched' => true, 'screen_attack_detected' => false,
            'confidence' => 0.99, 'liveness_score' => 0.95,
        ];
        $provider = $normalize->invoke($service, $valid, 200, 'passive', 'capture-1');
        $this->assertSame(Presensi::STATUS_ABSEN_VERIFIED, $decide->invoke($service, [], $provider)['status']);
        $invalid = [
            ['status' => 'verified', 'verified' => true],
            array_merge($valid, ['presensi_challenge_id' => 'other-capture']),
            array_merge($valid, ['liveness_mode' => 'active']),
            array_merge($valid, ['passive_liveness_passed' => 'true']),
            array_merge($valid, ['screen_attack_detected' => 'false']),
            array_merge($valid, ['confidence' => null]),
            array_merge($valid, ['liveness_score' => 95]),
        ];
        foreach ($invalid as $payload) {
            $provider = $normalize->invoke($service, $payload, 200, 'passive', 'capture-1');
            $this->assertSame(Presensi::STATUS_ABSEN_PENDING_REVIEW, $decide->invoke($service, [], $provider)['status']);
        }
        foreach ([['screen_attack_detected' => true], ['passive_liveness_passed' => false], ['liveness_score' => 0.3]] as $failure) {
            $provider = $normalize->invoke($service, array_merge($valid, $failure), 200, 'passive', 'capture-1');
            $this->assertSame(Presensi::STATUS_ABSEN_REJECTED, $decide->invoke($service, [], $provider)['status']);
        }
        $provider = $normalize->invoke($service, array_merge($valid, ['confidence' => 0.2]), 200, 'passive', 'capture-1');
        $this->assertSame(Presensi::STATUS_ABSEN_PENDING_REVIEW, $decide->invoke($service, [], $provider)['status']);
        $provider = $normalize->invoke($service, array_merge($valid, ['status' => 'rejected']), 200, 'passive', 'capture-1');
        $this->assertSame(Presensi::STATUS_ABSEN_REJECTED, $decide->invoke($service, [], $provider)['status']);
    }

    public function test_passive_evidence_uses_separate_api_fields_from_active_head_turns(): void
    {
        $method = new \ReflectionMethod(ServerSideFaceVerificationService::class, 'buildLivenessEvidenceParts');
        $method->setAccessible(true);
        $frames = array_map(function ($label) {
            return ['label' => $label, 'image' => 'data:image/jpeg;base64,' . base64_encode('test-image-data')];
        }, ['passive_start', 'passive_middle', 'passive_end']);
        $parts = $method->invoke(app(ServerSideFaceVerificationService::class), ['frames' => $frames], 'TEST-001');
        $this->assertSame(['liveness_passive_start_image', 'liveness_passive_middle_image', 'liveness_passive_end_image'], array_column($parts, 'name'));
    }
}
