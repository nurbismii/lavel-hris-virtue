<?php

namespace App\Services\Presensi;

use App\Models\Presensi;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ServerSideFaceVerificationService
{
    private const PASSIVE_MIN_SCORE = 70;

    public function verify(
        UploadedFile $selfieFile,
        User $user,
        string $referencePath,
        Request $request,
        array $challenge,
        array $faceResult,
        array $selfieAudit
    ): array {
        $reference = $this->resolveReferenceImage($referencePath, (string) $user->nik_karyawan);
        $passive = $this->runPassiveLivenessChecks($selfieFile->getRealPath(), $selfieAudit);
        $provider = $this->runProviderVerification(
            $selfieFile,
            $reference,
            $user,
            $request,
            $challenge,
            $faceResult,
            $selfieAudit,
            $passive
        );

        return $this->buildDecision($passive, $provider);
    }

    private function buildDecision(array $passive, ?array $provider): array
    {
        $failClosed = (bool) config('services.presensi_face.fail_closed', false);

        if ($provider) {
            if (($provider['status'] ?? null) === Presensi::STATUS_ABSEN_PENDING_REVIEW) {
                return [
                    'status' => Presensi::STATUS_ABSEN_PENDING_REVIEW,
                    'passed' => false,
                    'method' => 'server-side-provider-review',
                    'message' => $provider['message'] ?? 'Server-side face verification membutuhkan review manual.',
                    'provider' => $provider,
                    'passive_liveness' => $passive,
                ];
            }

            $providerPassed = (bool) ($provider['liveness_passed'] ?? false)
                && (bool) ($provider['face_matched'] ?? false);

            if ($providerPassed) {
                return [
                    'status' => Presensi::STATUS_ABSEN_VERIFIED,
                    'passed' => true,
                    'method' => 'server-side-provider',
                    'message' => 'Server-side liveness dan face verification berhasil.',
                    'provider' => $provider,
                    'passive_liveness' => $passive,
                ];
            }

            return [
                'status' => Presensi::STATUS_ABSEN_REJECTED,
                'passed' => false,
                'method' => 'server-side-provider',
                'message' => $provider['message'] ?? 'Server menolak liveness atau kecocokan wajah.',
                'provider' => $provider,
                'passive_liveness' => $passive,
            ];
        }

        if ($failClosed) {
            return [
                'status' => Presensi::STATUS_ABSEN_REJECTED,
                'passed' => false,
                'method' => 'server-side-provider-unavailable',
                'message' => 'Server-side face verification belum tersedia. Presensi ditolak sesuai mode fail-closed.',
                'provider' => null,
                'passive_liveness' => $passive,
            ];
        }


        return [
            'status' => Presensi::STATUS_ABSEN_PENDING_REVIEW,
            'passed' => false,
            'method' => 'server-side-passive-review',
            'message' => 'Presensi masuk antrean review karena provider server-side belum tersedia.',
            'provider' => null,
            'passive_liveness' => $passive,
        ];
    }

    private function runProviderVerification(
        UploadedFile $selfieFile,
        array $reference,
        User $user,
        Request $request,
        array $challenge,
        array $faceResult,
        array $selfieAudit,
        array $passive
    ): ?array {
        $endpoint = trim((string) config('services.presensi_face.endpoint'));

        if ($endpoint === '') {
            return null;
        }

        if (empty($reference['valid']) || empty($reference['absolute_path'])) {
            return [
                'liveness_passed' => false,
                'face_matched' => false,
                'message' => 'Foto referensi wajah tidak valid untuk verifikasi server-side.',
            ];
        }

        $livenessEvidence = $this->extractLivenessEvidence($request);
        $selfieHandle = null;
        $referenceHandle = null;

        try {
            $selfieHandle = fopen($selfieFile->getRealPath(), 'r');
            $referenceHandle = fopen($reference['absolute_path'], 'r');

            if (!$selfieHandle || !$referenceHandle) {
                return [
                    'liveness_passed' => false,
                    'face_matched' => false,
                    'message' => 'File selfie atau referensi tidak dapat dibaca oleh server verifier.',
                ];
            }

            $attendanceDate = (string) ($challenge['attendance_date'] ?? now()->toDateString());
            $absensiId = Presensi::where('nik_karyawan', $user->nik_karyawan)
                ->whereDate('tanggal', $attendanceDate)
                ->value('id');
            $providerScreenSpoofScore = $this->providerScreenSpoofScore($request);
            $livenessEvidenceParts = $this->buildLivenessEvidenceParts($livenessEvidence, (string) $user->nik_karyawan);

            \Log::info('FACE PROVIDER REQUEST', [
                'endpoint' => $endpoint,
                'token_exists' => !empty(config('services.presensi_face.token')),
                'reference_valid' => $reference['valid'] ?? false,
                'selfie_valid' => File::isFile((string) $selfieFile->getRealPath()),
                'liveness_action' => (string) ($challenge['liveness_action'] ?? 'turn_left_right'),
                'liveness_fields' => array_values(array_map(fn ($part) => $part['name'] ?? null, $livenessEvidenceParts)),
                'reference_path' => $reference['absolute_path'] ?? null,
                'selfie_path' => $selfieFile->getRealPath(),
                'presensi_challenge_id' => $challenge['id'] ?? null,
                'screen_spoof_score' => $providerScreenSpoofScore,
            ]);

            $multipart = [
                [
                    'name' => 'selfie_image',
                    'contents' => $selfieHandle,
                    'filename' => 'selfie-' . $user->nik_karyawan . '.jpg',
                ],
                [
                    'name' => 'reference_image',
                    'contents' => $referenceHandle,
                    'filename' => 'reference-' . $user->nik_karyawan . '.jpg',
                ],
                [
                    'name' => 'absensi_id',
                    'contents' => (string) ($absensiId ?? ''),
                ],
                [
                    'name' => 'nik_karyawan',
                    'contents' => (string) $user->nik_karyawan,
                ],
                [
                    'name' => 'tanggal',
                    'contents' => $attendanceDate,
                ],
                [
                    'name' => 'presensi_challenge_id',
                    'contents' => (string) ($challenge['id'] ?? ''),
                ],
                [
                    'name' => 'liveness_action',
                    'contents' => (string) ($challenge['liveness_action'] ?? 'turn_left_right'),
                ],
                [
                    'name' => 'screen_spoof_score',
                    'contents' => (string) $providerScreenSpoofScore,
                ],
                [
                    'name' => 'payload',
                    'contents' => json_encode([
                        'nik_karyawan' => (string) $user->nik_karyawan,
                        'absensi_id' => $absensiId,
                        'tanggal' => $attendanceDate,
                        'challenge_id' => $challenge['id'] ?? null,
                        'challenge_issued_at' => $challenge['issued_at'] ?? null,
                        'liveness_challenge' => [
                            'action' => $challenge['liveness_action'] ?? 'turn_left_right',
                            'label' => $challenge['liveness_label'] ?? null,
                        ],
                        'client_liveness' => $faceResult['liveness'] ?? null,
                        'liveness_evidence_summary' => $this->summarizeLivenessEvidence($livenessEvidence),
                        'client_face_distance' => $faceResult['distance'] ?? null,
                        'client_detection_score' => $faceResult['detection_score'] ?? null,
                        'screen_spoof_score' => $providerScreenSpoofScore,
                        'client_screen_spoof_score_raw' => (float) $request->input('screen_spoof_score', 0),
                        'selfie_sha256' => $selfieAudit['hash'] ?? null,
                        'reference_sha256' => $reference['hash'] ?? null,
                        'passive_liveness' => $passive,
                        'ip_hash' => hash('sha256', (string) $request->ip()),
                        'user_agent_hash' => hash('sha256', (string) $request->userAgent()),
                    ]),
                ],
            ];

            foreach ($livenessEvidenceParts as $part) {
                $multipart[] = $part;
            }

            $response = (new Client([
                'timeout' => (float) config('services.presensi_face.timeout', 8),
                'connect_timeout' => (float) config('services.presensi_face.connect_timeout', 2),
                'http_errors' => false,
            ]))->post($endpoint, [
                'headers' => $this->providerHeaders(),
                'multipart' => $multipart,
            ]);

            $body = (string) $response->getBody();
            $payload = json_decode($body, true);

            if (!is_array($payload)) {
                \Log::warning('FACE PROVIDER NON JSON RESPONSE', [
                    'status' => $response->getStatusCode(),
                    'body_preview' => Str::limit($body, 500),
                ]);

                return null;
            }

            \Log::info('FACE PROVIDER RESPONSE', [
                'status' => $response->getStatusCode(),
                'provider_status' => $payload['status'] ?? null,
                'verified' => $payload['verified'] ?? null,
                'face_matched' => $payload['face_matched'] ?? null,
                'active_liveness_passed' => $payload['active_liveness_passed'] ?? null,
                'challenge_passed' => $payload['challenge_passed'] ?? null,
                'screen_attack_detected' => $payload['screen_attack_detected'] ?? null,
                'confidence' => $payload['confidence'] ?? null,
                'liveness_score' => $payload['liveness_score'] ?? null,
                'message' => $payload['message'] ?? null,
                'liveness_message' => data_get($payload, 'extra.liveness.message'),
                'liveness_details' => data_get($payload, 'extra.liveness.details'),
                'error' => Str::limit((string) data_get($payload, 'extra.error', ''), 300),
            ]);

            return $this->normalizeProviderPayload($payload, $response->getStatusCode());
        } catch (GuzzleException $exception) {
            \Log::error('FACE PROVIDER GUZZLE ERROR', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        } finally {
            if (is_resource($selfieHandle)) {
                fclose($selfieHandle);
            }

            if (is_resource($referenceHandle)) {
                fclose($referenceHandle);
            }
        }
    }

    private function normalizeProviderPayload(array $payload, int $httpStatus): array
    {
        $status = $payload['status'] ?? null;
        $requireActiveLiveness = (bool) config('services.presensi_face.require_active_liveness', true);
        $minConfidence = (float) config('services.presensi_face.min_confidence', 0.78);
        $minLivenessScore = (float) config('services.presensi_face.min_liveness_score', 0.78);

        $verified = (bool) ($payload['verified'] ?? false);
        $providerFaceMatched = (bool) ($payload['face_matched'] ?? false);

        $confidence = $this->normalizedProviderConfidence($payload);
        $score = isset($payload['score']) && is_numeric($payload['score']) ? (float) $payload['score'] : null;
        $livenessScore = isset($payload['liveness_score']) ? (float) $payload['liveness_score'] : null;

        $distance = $payload['distance'] ?? null;
        $threshold = $payload['threshold'] ?? null;

        $faceMatched = $httpStatus >= 200
            && $httpStatus < 300
            && ($providerFaceMatched || $verified || $status === 'verified')
            && ($confidence === null || $confidence >= $minConfidence);

        $activeLivenessPassed = (bool) ($payload['active_liveness_passed'] ?? false)
            || (bool) ($payload['challenge_passed'] ?? false);
        $passiveLivenessPassed = (bool) ($payload['liveness_passed'] ?? false);
        $screenAttackDetected = (bool) ($payload['screen_attack_detected'] ?? false)
            || (bool) ($payload['spoof_detected'] ?? false);
        $livenessPassed = $httpStatus >= 200
            && $httpStatus < 300
            && !$screenAttackDetected
            && ($livenessScore === null || $livenessScore >= $minLivenessScore)
            && ($requireActiveLiveness ? $activeLivenessPassed : ($activeLivenessPassed || $passiveLivenessPassed || $status === 'verified'));

        return [
            'http_status' => $httpStatus,
            'provider' => $payload['provider'] ?? ($payload['method'] ?? 'deepface'),
            'liveness_passed' => $livenessPassed,
            'active_liveness_passed' => $activeLivenessPassed,
            'face_matched' => $faceMatched,
            'confidence' => $confidence,
            'score' => $score,
            'liveness_score' => $livenessScore,
            'screen_attack_detected' => $screenAttackDetected,
            'distance' => $distance,
            'threshold' => $threshold,
            'status' => $status,
            'verified' => $verified,
            'message' => $payload['message'] ?? null,
            'raw' => $payload,
        ];
    }

    private function normalizedProviderConfidence(array $payload): ?float
    {
        if (isset($payload['confidence']) && is_numeric($payload['confidence'])) {
            $confidence = (float) $payload['confidence'];
        } elseif (isset($payload['score']) && is_numeric($payload['score'])) {
            $confidence = (float) $payload['score'];
        } else {
            return null;
        }

        if ($confidence > 1) {
            $confidence = $confidence / 100;
        }

        return round(max(0, min(1, $confidence)), 4);
    }

    private function extractLivenessEvidence(Request $request): array
    {
        $evidence = json_decode((string) $request->input('face_liveness_evidence'), true);

        if (!is_array($evidence) || !is_array($evidence['frames'] ?? null)) {
            return ['frames' => []];
        }

        return $evidence;
    }

    private function summarizeLivenessEvidence(array $evidence): array
    {
        return [
            'frames' => collect($evidence['frames'] ?? [])
                ->map(function ($frame) {
                    return [
                        'label' => $frame['label'] ?? null,
                        'captured_at_ms' => $frame['captured_at_ms'] ?? null,
                        'ear' => $frame['ear'] ?? null,
                        'yaw' => $frame['yaw'] ?? null,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    private function buildLivenessEvidenceParts(array $evidence, string $nikKaryawan): array
    {
        $parts = [];
        $fieldNames = [
            'center' => 'liveness_center_image',
            'turn_left' => 'liveness_turn_left_image',
            'turn_right' => 'liveness_turn_right_image',
        ];

        foreach (($evidence['frames'] ?? []) as $frame) {
            $label = (string) ($frame['label'] ?? '');

            if (!array_key_exists($label, $fieldNames)) {
                continue;
            }

            $binary = $this->decodeEvidenceImage((string) ($frame['image'] ?? ''));

            if ($binary === null) {
                continue;
            }

            $parts[] = [
                'name' => $fieldNames[$label],
                'contents' => $binary,
                'filename' => 'liveness-' . $nikKaryawan . '-' . $label . '.jpg',
                'headers' => [
                    'Content-Type' => 'image/jpeg',
                ],
            ];
        }

        return $parts;
    }

    private function decodeEvidenceImage(string $dataUrl): ?string
    {
        if (!preg_match('/^data:image\/jpeg;base64,([A-Za-z0-9+\/=]+)$/', $dataUrl, $matches)) {
            return null;
        }

        $binary = base64_decode($matches[1], true);

        if ($binary === false || strlen($binary) > 350000) {
            return null;
        }

        return $binary;
    }

    private function providerHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
        ];

        $token = trim((string) config('services.presensi_face.token'));

        if ($token !== '') {
            $headers['X-Verify-Token'] = $token;
        }

        return $headers;
    }

    private function providerScreenSpoofScore(Request $request): float
    {
        $score = (float) $request->input('screen_spoof_score', 0);

        if ($score > 1) {
            $score = $score / 100;
        }

        return round(max(0, min(1, $score)), 4);
    }

    private function resolveReferenceImage(string $referencePath, string $nikKaryawan): array
    {
        $normalizedPath = str_replace('\\', '/', ltrim($referencePath, '/'));

        $allowedDirectories = [
            'face-reference/' . $nikKaryawan . '/',
            'assets/face-reference/' . $nikKaryawan . '/',
        ];

        $isAllowed = false;

        foreach ($allowedDirectories as $directory) {
            if (Str::startsWith($normalizedPath, $directory)) {
                $isAllowed = true;
                break;
            }
        }

        if (str_contains($normalizedPath, '..') || !$isAllowed) {
            return [
                'absolute_path' => null,
                'hash' => null,
                'valid' => false,
            ];
        }

        $absolutePath = public_path($normalizedPath);

        return [
            'absolute_path' => $absolutePath,
            'hash' => File::isFile($absolutePath) ? hash_file('sha256', $absolutePath) : null,
            'valid' => File::isFile($absolutePath),
        ];
    }

    private function runPassiveLivenessChecks(?string $path, array $selfieAudit): array
    {
        $reasons = [];
        $score = 100;

        if (!$path || !File::isFile($path)) {
            return [
                'passed' => false,
                'score' => 0,
                'reasons' => ['selfie_file_missing'],
            ];
        }

        if (!extension_loaded('gd')) {
            return [
                'passed' => false,
                'score' => 0,
                'reasons' => ['gd_extension_missing'],
            ];
        }

        $stats = $this->imageStats($path, $selfieAudit['mime_type'] ?? null);

        if (!$stats) {
            return [
                'passed' => false,
                'score' => 0,
                'reasons' => ['image_decode_failed'],
            ];
        }

        if ($stats['brightness'] < 35 || $stats['brightness'] > 225) {
            $score -= 25;
            $reasons[] = 'brightness_out_of_range';
        }

        if ($stats['variance'] < 180) {
            $score -= 25;
            $reasons[] = 'low_texture_variance';
        }

        if ($stats['edge_score'] < 9) {
            $score -= 25;
            $reasons[] = 'low_edge_detail';
        }

        if ($stats['sample_count'] < 100) {
            $score -= 20;
            $reasons[] = 'insufficient_image_samples';
        }

        return [
            'passed' => $score >= self::PASSIVE_MIN_SCORE,
            'score' => max(0, $score),
            'reasons' => $reasons,
            'stats' => $stats,
        ];
    }

    private function imageStats(string $path, ?string $mimeType): ?array
    {
        $image = $this->createImageResource($path, $mimeType);

        if (!$image) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $stepX = max(1, (int) floor($width / 32));
        $stepY = max(1, (int) floor($height / 32));
        $sum = 0;
        $sumSquare = 0;
        $edgeSum = 0;
        $sampleCount = 0;
        $previousLum = null;

        for ($y = 0; $y < $height; $y += $stepY) {
            for ($x = 0; $x < $width; $x += $stepX) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $lum = (0.2126 * $r) + (0.7152 * $g) + (0.0722 * $b);

                $sum += $lum;
                $sumSquare += $lum * $lum;

                if ($previousLum !== null) {
                    $edgeSum += abs($lum - $previousLum);
                }

                $previousLum = $lum;
                $sampleCount++;
            }
        }

        imagedestroy($image);

        if ($sampleCount === 0) {
            return null;
        }

        $mean = $sum / $sampleCount;
        $variance = ($sumSquare / $sampleCount) - ($mean * $mean);

        return [
            'width' => $width,
            'height' => $height,
            'brightness' => round($mean, 2),
            'variance' => round(max(0, $variance), 2),
            'edge_score' => round($edgeSum / max(1, $sampleCount - 1), 2),
            'sample_count' => $sampleCount,
        ];
    }

    private function createImageResource(string $path, ?string $mimeType)
    {
        if ($mimeType === 'image/jpeg') {
            return @imagecreatefromjpeg($path);
        }

        if ($mimeType === 'image/png') {
            return @imagecreatefrompng($path);
        }

        if ($mimeType === 'image/webp' && function_exists('imagecreatefromwebp')) {
            return @imagecreatefromwebp($path);
        }

        return null;
    }
}
