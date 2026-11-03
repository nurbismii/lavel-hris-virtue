<?php

namespace App\Services\Presensi;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class NativeGpsProof
{
    private const WINDOW = 45;

    public static function supported(Request $request): bool
    {
        return preg_match('/\bAndroid\b/i', (string) $request->userAgent())
            && preg_match('/(?:^|\s)VPEOPLE_APP\/([0-9]+(?:\.[0-9]+){2})(?:\s|$)/i', (string) $request->userAgent(), $matches)
            && version_compare($matches[1], '1.0.7', '>=');
    }

    private function binding(Request $request): string
    {
        return hash('sha256', $request->user()->getAuthIdentifier() . '|' . $request->session()->getId());
    }

    public function issue(Request $request, string $purpose, string $attendanceId): array
    {
        $nonce = bin2hex(random_bytes(32));
        $binding = $this->binding($request);
        Cache::put('gps:nonce:' . $binding . ':' . $purpose, [$nonce, $attendanceId], self::WINDOW);
        $key = Cache::get('gps:key:' . $binding);
        return ['nonce' => $nonce, 'key' => $key['id'] ?? 'new'];
    }

    public function verifiedFix(Request $request, string $purpose, AndroidGpsAttestation $attestation): array
    {
        $proof = $request->input('gps_proof');
        if (is_string($proof) && strlen($proof) <= 65536) {
            $proof = json_decode($proof, true, 8);
        }
        if (!is_array($proof) || !is_string($proof['data'] ?? null) || strlen($proof['data']) > 160
            || !preg_match('/\Av1\|([0-9a-f]{64})\|(-?\d{1,9})\|(-?\d{1,9})\|(\d{1,7})\|(\d{1,6})\z/', $proof['data'], $m)
            || !is_string($proof['key'] ?? null) || !preg_match('/\A[0-9a-f]{64}\z/', $proof['key'])
            || !is_string($proof['signature'] ?? null) || strlen($proof['signature']) > 128) {
            self::reject('Bukti GPS perangkat tidak tersedia. Gunakan aplikasi Android terbaru dan aktifkan lokasi akurat.');
        }
        $nonce = $m[1];
        $binding = $this->binding($request);
        $nonceKey = 'gps:nonce:' . $binding . ':' . $purpose;
        $expected = [$nonce, $request->input('attendance_challenge_id')];
        if (Cache::get($nonceKey) !== $expected) {
            self::reject('Bukti GPS sudah kedaluwarsa atau tidak sesuai sesi. Silakan coba lagi.');
        }
        $key = Cache::get('gps:key:' . $binding);
        $enrolling = !$key || !hash_equals($key['id'], $proof['key']);
        if ($enrolling) {
            $key = $attestation->verifyChain($proof['chain'] ?? null, $nonce, $proof['key']);
        }
        $signature = base64_decode($proof['signature'], true);
        if (!$signature || openssl_verify($proof['data'], $signature, $key['pem'], OPENSSL_ALGO_SHA256) !== 1) {
            self::reject('Tanda tangan GPS tidak valid. Muat ulang halaman presensi.');
        }
        $attestation->checkRevocation($key['serials']);
        if ($key['expires'] <= time()) {
            self::reject('Sertifikat perangkat kedaluwarsa. Muat ulang halaman presensi.');
        }
        $lat = (int) $m[2] / 1e6;
        $lon = (int) $m[3] / 1e6;
        $accuracy = (int) $m[4] / 100;
        $speed = (int) $m[5] / 100;
        if (abs($lat) > 90 || abs($lon) > 180 || $accuracy <= 0 || $accuracy > 200 || $speed > 50) {
            self::reject('Pembacaan GPS belum layak untuk presensi. Tunggu lokasi akurat dan stabil.');
        }
        // Consuming under a lock prevents replay without leaving a cache file per successful fix.
        $consumed = Cache::lock('gps:consume:' . $binding . ':' . $purpose, 5)->get(function () use ($nonceKey, $expected) {
            if (Cache::get($nonceKey) !== $expected) return false;
            Cache::forget($nonceKey);
            return true;
        });
        if (!$consumed) {
            self::reject('Bukti GPS sudah digunakan. Ambil lokasi baru lalu coba lagi.');
        }
        if ($enrolling) {
            Cache::put('gps:key:' . $binding, $key, min(86400, $key['expires'] - time()));
        }
        $isLog = $purpose === 'log';
        return [
            $isLog ? 'lat' : 'lat_user' => $lat,
            $isLog ? 'long' : 'long_user' => $lon,
            'accuracy' => $accuracy, 'speed' => $speed,
        ];
    }

    public static function reject(string $message): void
    {
        throw ValidationException::withMessages(['gps_proof' => $message]);
    }
}
