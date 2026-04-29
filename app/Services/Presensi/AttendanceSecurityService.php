<?php

namespace App\Services\Presensi;

use App\Models\Presensi;
use App\Models\LogPresensi;
use App\Models\LokasiAbsen;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceSecurityService
{
    private const CACHE_PREFIX = 'presensi:challenge:';
    private const CHALLENGE_TTL_SECONDS = 300;
    private const MIN_CAPTURE_DELAY_MS = 900;
    private const FACE_DISTANCE_THRESHOLD = 0.5;
    private const MIN_DETECTION_SCORE = 0.78;
    private const MAX_ROLL_ANGLE = 12;
    private const MIN_SELFIE_BYTES = 15360;
    private const MAX_SELFIE_BYTES = 4194304;
    private const MIN_SELFIE_DIMENSION = 240;
    private const MAX_SELFIE_DIMENSION = 2000;
    private const GPS_EVIDENCE_WINDOW_SECONDS = 120;
    private const MAX_SCREEN_SPOOF_SCORE = 45;
    private const LIVENESS_ACTION_TURN_LEFT_RIGHT = 'turn_left_right';

    public function issueChallenge(User $user, Request $request, string $attendanceDate, ?string $type = null): array
    {
        $issuedAt = now();
        $token = Str::random(64);
        $payload = [
            'id' => (string) Str::uuid(),
            'token' => $token,
            'user_id' => (string) $user->id,
            'nik_karyawan' => (string) $user->nik_karyawan,
            'attendance_date' => $attendanceDate,
            'type' => $type,
            'user_agent_hash' => $this->hashUserAgent($request),
            'issued_at' => $issuedAt->toIso8601String(),
            'expires_at' => $issuedAt->copy()->addSeconds($this->challengeTtlSeconds())->toIso8601String(),
            'min_capture_delay_ms' => $this->minCaptureDelayMs(),
            'liveness_action' => self::LIVENESS_ACTION_TURN_LEFT_RIGHT,
            'liveness_label' => 'Hadap tengah, hadap kiri, lalu hadap kanan',
        ];

        Cache::put($this->challengeCacheKey($token), $payload, $this->challengeTtlSeconds());

        return [
            'id' => $payload['id'],
            'token' => $token,
            'issued_at' => $payload['issued_at'],
            'expires_at' => $payload['expires_at'],
            'min_capture_delay_ms' => $payload['min_capture_delay_ms'],
            'liveness_action' => $payload['liveness_action'],
            'liveness_label' => $payload['liveness_label'],
            'liveness_required_frames' => ['center', 'turn_left', 'turn_right'],
        ];
    }

    public function consumeChallenge(Request $request, User $user, string $attendanceDate, string $type): array
    {
        $token = (string) $request->input('attendance_challenge_token');
        $challengeId = (string) $request->input('attendance_challenge_id');

        $payload = $token !== '' ? Cache::get($this->challengeCacheKey($token)) : null;

        if ($token !== '') {
            Cache::forget($this->challengeCacheKey($token));
        }

        if (!is_array($payload)) {
            $this->fail('Sesi keamanan presensi sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.');
        }

        if (
            !hash_equals((string) ($payload['id'] ?? ''), $challengeId)
            || !hash_equals((string) ($payload['user_id'] ?? ''), (string) $user->id)
            || !hash_equals((string) ($payload['nik_karyawan'] ?? ''), (string) $user->nik_karyawan)
            || !hash_equals((string) ($payload['attendance_date'] ?? ''), $attendanceDate)
        ) {
            $this->fail('Sesi keamanan presensi tidak sesuai dengan akun atau tanggal presensi.');
        }

        if (!empty($payload['type']) && !hash_equals((string) $payload['type'], $type)) {
            $this->fail('Sesi keamanan presensi tidak sesuai dengan jenis presensi.');
        }

        if (!hash_equals((string) ($payload['user_agent_hash'] ?? ''), $this->hashUserAgent($request))) {
            $this->fail('Perangkat presensi berubah. Muat ulang halaman lalu coba lagi.');
        }

        if (Carbon::parse($payload['expires_at'])->isPast()) {
            $this->fail('Sesi keamanan presensi sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.');
        }

        return $payload;
    }

    public function validateFacePayload(Request $request, array $challenge): array
    {
        $meta = json_decode((string) $request->input('face_verification_meta'), true);

        if (!is_array($meta)) {
            $this->fail('Metadata verifikasi wajah tidak valid. Ambil ulang selfie.');
        }

        $source = (string) ($meta['source'] ?? '');

        if ($source !== 'live-camera') {
            $this->fail('Presensi hanya menerima selfie dari kamera live, bukan upload manual.');
        }

        if (!hash_equals((string) ($meta['challenge_id'] ?? ''), (string) ($challenge['id'] ?? ''))) {
            $this->fail('Challenge verifikasi wajah tidak cocok. Muat ulang halaman lalu coba lagi.');
        }

        $faceVerified = filter_var($request->input('face_verified'), FILTER_VALIDATE_BOOLEAN);
        $distance = (float) $request->input('face_distance');
        $metaDistance = isset($meta['distance']) ? (float) $meta['distance'] : null;
        $detectionCount = (int) $request->input('face_detection_count');
        $detectionScore = isset($meta['detection_score']) ? (float) $meta['detection_score'] : null;
        $rollAngle = isset($meta['roll_angle']) ? abs((float) $meta['roll_angle']) : null;
        $elapsedMs = isset($meta['challenge_elapsed_ms']) ? (int) $meta['challenge_elapsed_ms'] : 0;
        $liveness = $this->validateLivenessPayload($request, $meta, $challenge);

        if (!$faceVerified || $detectionCount !== 1) {
            $this->fail('Selfie harus tervalidasi dan memuat tepat satu wajah.');
        }

        if ($distance > self::FACE_DISTANCE_THRESHOLD || $metaDistance === null || abs($distance - $metaDistance) > 0.00001) {
            $this->fail('Jarak kecocokan wajah tidak valid. Ambil ulang selfie.');
        }

        if ($detectionScore === null || $detectionScore < self::MIN_DETECTION_SCORE) {
            $this->fail('Kualitas deteksi wajah belum cukup kuat. Ambil ulang selfie dengan pencahayaan lebih baik.');
        }

        if ($rollAngle === null || $rollAngle > self::MAX_ROLL_ANGLE) {
            $this->fail('Posisi wajah belum cukup lurus. Ambil ulang selfie.');
        }

        if (($meta['frame_state'] ?? null) !== 'green' || $elapsedMs < (int) ($challenge['min_capture_delay_ms'] ?? self::MIN_CAPTURE_DELAY_MS)) {
            $this->fail('Selfie belum melewati validasi live camera. Ambil ulang selfie.');
        }

        try {
            $verifiedAt = !empty($meta['verified_at_client']) ? Carbon::parse($meta['verified_at_client']) : null;
            $issuedAt = Carbon::parse($challenge['issued_at']);
            $expiresAt = Carbon::parse($challenge['expires_at']);
        } catch (\Throwable $exception) {
            $this->fail('Waktu verifikasi wajah tidak valid. Muat ulang halaman lalu coba lagi.');
        }

        if (!$verifiedAt || $verifiedAt->lt($issuedAt) || $verifiedAt->gt($expiresAt)) {
            $this->fail('Waktu verifikasi wajah tidak valid. Muat ulang halaman lalu coba lagi.');
        }

        return [
            'meta' => $meta,
            'distance' => $distance,
            'detection_score' => $detectionScore,
            'roll_angle' => $rollAngle,
            'elapsed_ms' => $elapsedMs,
            'liveness' => $liveness,
        ];
    }

    private function validateLivenessPayload(Request $request, array $meta, array $challenge): array
    {
        $challengeId = (string) ($challenge['id'] ?? '');
        $expectedAction = (string) ($challenge['liveness_action'] ?? self::LIVENESS_ACTION_TURN_LEFT_RIGHT);
        $client = is_array($meta['client_liveness'] ?? null) ? $meta['client_liveness'] : [];
        $passed = filter_var($request->input('face_liveness_passed'), FILTER_VALIDATE_BOOLEAN);
        $score = (float) $request->input('face_liveness_score');
        $screenSpoofScore = (float) $request->input('screen_spoof_score');
        $evidence = json_decode((string) $request->input('face_liveness_evidence'), true);

        if (!$passed || !($client['passed'] ?? false)) {
            $this->fail('Liveness belum valid. Ikuti instruksi hadap wajah langsung di depan kamera.');
        }

        if (!hash_equals($challengeId, (string) $request->input('presensi_challenge_id'))) {
            $this->fail('Challenge liveness tidak cocok. Muat ulang halaman lalu coba lagi.');
        }

        if (!hash_equals($expectedAction, (string) $request->input('presensi_challenge_action'))) {
            $this->fail('Instruksi liveness tidak cocok. Muat ulang halaman lalu coba lagi.');
        }

        if (!hash_equals($expectedAction, (string) $request->input('face_liveness_type'))) {
            $this->fail('Jenis liveness tidak cocok. Muat ulang halaman lalu coba lagi.');
        }

        if (
            !hash_equals($challengeId, (string) ($client['challenge_id'] ?? ''))
            || !hash_equals($expectedAction, (string) ($client['challenge_action'] ?? ''))
        ) {
            $this->fail('Metadata liveness tidak cocok dengan challenge server.');
        }

        if ($screenSpoofScore >= self::MAX_SCREEN_SPOOF_SCORE) {
            $this->fail('Selfie terindikasi berasal dari layar atau foto. Gunakan wajah langsung di depan kamera.');
        }

        if (!is_array($evidence) || !is_array($evidence['frames'] ?? null)) {
            $this->fail('Bukti liveness tidak lengkap. Ulangi verifikasi kamera.');
        }

        $frames = collect($evidence['frames'])->keyBy(fn($frame) => (string) ($frame['label'] ?? ''));
        $requiredFrames = ['center', 'turn_left', 'turn_right'];

        foreach ($requiredFrames as $frameLabel) {
            $frame = $frames->get($frameLabel);

            if (!is_array($frame) || empty($frame['image']) || !is_string($frame['image'])) {
                $this->fail('Bukti liveness tidak lengkap. Ulangi verifikasi kamera.');
            }

            if (!preg_match('/^data:image\/jpeg;base64,[A-Za-z0-9+\/=]+$/', $frame['image'])) {
                $this->fail('Format bukti liveness tidak valid. Ulangi verifikasi kamera.');
            }
        }

        $centerAt = $this->livenessFrameTimestamp($frames->get('center'));
        $turnLeftAt = $this->livenessFrameTimestamp($frames->get('turn_left'));
        $turnRightAt = $this->livenessFrameTimestamp($frames->get('turn_right'));

        if ($centerAt <= 0 || $turnLeftAt <= $centerAt || $turnRightAt <= $turnLeftAt || ($turnRightAt - $centerAt) > 15000) {
            $this->fail('Urutan liveness tidak valid. Ulangi verifikasi kamera.');
        }

        return [
            'type' => $expectedAction,
            'passed' => true,
            'score' => $score,
            'screen_spoof_score' => $screenSpoofScore,
            'message' => (string) $request->input('face_liveness_message'),
            'evidence_summary' => [
                'frames' => $requiredFrames,
                'duration_ms' => $turnRightAt - $centerAt,
            ],
        ];
    }

    private function livenessFrameTimestamp($frame): float
    {
        if (!is_array($frame) || !isset($frame['captured_at_ms']) || !is_numeric($frame['captured_at_ms'])) {
            return 0;
        }

        return (float) $frame['captured_at_ms'];
    }

    public function validateSelfieImage(UploadedFile $file, User $user, ?string $referencePath): array
    {
        $realPath = $file->getRealPath();

        if (!$realPath || !File::isFile($realPath)) {
            $this->fail('Selfie kamera tidak terbaca. Ambil ulang selfie.');
        }

        $size = File::size($realPath);

        if ($size < self::MIN_SELFIE_BYTES || $size > self::MAX_SELFIE_BYTES) {
            $this->fail('Ukuran selfie tidak wajar. Ambil ulang selfie langsung dari kamera.');
        }

        $imageInfo = @getimagesize($realPath);

        if (!is_array($imageInfo)) {
            $this->fail('Selfie kamera bukan gambar yang valid. Ambil ulang selfie.');
        }

        [$width, $height] = $imageInfo;
        $mimeType = (string) ($imageInfo['mime'] ?? $file->getMimeType());

        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $this->fail('Format selfie tidak didukung. Ambil ulang selfie.');
        }

        if (
            min($width, $height) < self::MIN_SELFIE_DIMENSION
            || max($width, $height) > self::MAX_SELFIE_DIMENSION
        ) {
            $this->fail('Dimensi selfie tidak sesuai. Ambil ulang selfie dari kamera.');
        }

        $hash = hash_file('sha256', $realPath);

        if ($this->matchesReferenceImage($hash, $referencePath)) {
            $this->fail('Selfie tidak boleh sama dengan foto referensi. Ambil selfie live dari kamera.');
        }

        if (
            Presensi::where('nik_karyawan', $user->nik_karyawan)
                ->where('face_selfie_hash', $hash)
                ->exists()
        ) {
            $this->fail('Selfie ini sudah pernah digunakan. Ambil selfie baru dari kamera.');
        }

        return [
            'hash' => $hash,
            'mime_type' => $mimeType,
            'size' => $size,
            'width' => (int) $width,
            'height' => (int) $height,
        ];
    }

    public function validateRecentGpsEvidence(Request $request, User $user, LokasiAbsen $lokasi): void
    {
        $recentLog = LogPresensi::where('nik_karyawan', $user->nik_karyawan)
            ->where('created_at', '>=', now()->subSeconds(self::GPS_EVIDENCE_WINDOW_SECONDS))
            ->where('ip_address', $request->ip())
            ->where('user_agent', $request->userAgent())
            ->latest('created_at')
            ->first();

        if (!$recentLog) {
            $this->fail('Bukti GPS live belum lengkap. Tunggu lokasi stabil beberapa detik lalu coba lagi.');
        }

        if ((float) $recentLog->accuracy > 60 || (float) $recentLog->speed > 50) {
            $this->fail('Bukti GPS live tidak valid. Tunggu GPS stabil lalu coba lagi.');
        }

        $submittedDistance = $this->calculateDistance(
            (float) $recentLog->lat,
            (float) $recentLog->long,
            (float) $request->input('lat_user'),
            (float) $request->input('long_user')
        );
        $allowedDrift = max(
            20,
            (float) $request->input('accuracy') + (float) $recentLog->accuracy + 10
        );

        if ($submittedDistance > $allowedDrift) {
            $this->fail('Lokasi submit tidak cocok dengan jejak GPS live. Tunggu lokasi stabil lalu coba lagi.');
        }

        $officeDistance = $this->calculateDistance(
            (float) $recentLog->lat,
            (float) $recentLog->long,
            (float) $lokasi->lat,
            (float) $lokasi->long
        );

        if ($officeDistance > (float) $lokasi->radius) {
            $this->fail('Jejak GPS live berada di luar radius presensi.');
        }
    }

    public function buildStoredMeta(
        Request $request,
        array $challenge,
        array $faceResult,
        array $selfieAudit,
        ?array $serverVerification = null
    ): string
    {
        $meta = $faceResult['meta'];
        $meta['server_validation'] = [
            'version' => 1,
            'passed' => true,
            'challenge_id' => $challenge['id'],
            'challenge_issued_at' => $challenge['issued_at'],
            'challenge_expires_at' => $challenge['expires_at'],
            'selfie_sha256' => $selfieAudit['hash'],
            'selfie_mime_type' => $selfieAudit['mime_type'],
            'selfie_size' => $selfieAudit['size'],
            'selfie_width' => $selfieAudit['width'],
            'selfie_height' => $selfieAudit['height'],
            'validated_at' => now()->toIso8601String(),
            'ip_hash' => hash('sha256', (string) $request->ip()),
            'user_agent_hash' => $this->hashUserAgent($request),
        ];

        if ($serverVerification !== null) {
            $meta['server_face_verification'] = [
                'status' => $serverVerification['status'] ?? null,
                'passed' => (bool) ($serverVerification['passed'] ?? false),
                'method' => $serverVerification['method'] ?? null,
                'message' => $serverVerification['message'] ?? null,
                'provider' => $serverVerification['provider'] ?? null,
                'passive_liveness' => $serverVerification['passive_liveness'] ?? null,
                'validated_at' => now()->toIso8601String(),
            ];
        }

        return json_encode($meta);
    }

    private function matchesReferenceImage(string $selfieHash, ?string $referencePath): bool
    {
        if (blank($referencePath)) {
            return false;
        }

        $normalizedPath = str_replace('\\', '/', ltrim($referencePath, '/'));

        if (str_contains($normalizedPath, '..') || !Str::startsWith($normalizedPath, 'face-reference/')) {
            return false;
        }

        $absolutePath = public_path($normalizedPath);

        return File::isFile($absolutePath) && hash_file('sha256', $absolutePath) === $selfieHash;
    }

    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($dLon / 2)
            * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function challengeCacheKey(string $token): string
    {
        return self::CACHE_PREFIX . hash('sha256', $token);
    }

    private function hashUserAgent(Request $request): string
    {
        return hash('sha256', (string) $request->userAgent());
    }

    private function challengeTtlSeconds(): int
    {
        return max(60, (int) env('PRESENSI_CHALLENGE_TTL_SECONDS', self::CHALLENGE_TTL_SECONDS));
    }

    private function minCaptureDelayMs(): int
    {
        return max(500, (int) env('PRESENSI_MIN_CAPTURE_DELAY_MS', self::MIN_CAPTURE_DELAY_MS));
    }

    private function fail(string $message): void
    {
        throw ValidationException::withMessages([
            'presensi_security' => $message,
        ]);
    }
}
