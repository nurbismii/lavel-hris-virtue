<?php

namespace App\Services\Presensi;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use phpseclib3\File\ASN1;

class AndroidGpsAttestation
{
    private const OID = '1.3.6.1.4.1.11129.2.1.17';
    private const STATUS_URL = 'https://android.googleapis.com/attestation/status';

    private function invalid(): void
    {
        NativeGpsProof::reject('Perangkat atau aplikasi tidak lolos verifikasi perangkat keras. Gunakan APK resmi pada Android yang didukung, atau hubungi HR.');
    }

    public function verifyChain($chain, string $nonce, string $keyId): array
    {
        if (!is_array($chain) || count($chain) < 2 || count($chain) > 8) {
            $this->invalid();
        }
        $certificates = [];
        $serials = [];
        $expires = PHP_INT_MAX;
        foreach (array_values($chain) as $i => $encoded) {
            if (!is_string($encoded) || strlen($encoded) > 12288 || !($der = base64_decode($encoded, true))) {
                $this->invalid();
            }
            $pem = "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END CERTIFICATE-----\n";
            $certificate = @openssl_x509_parse($pem, false);
            if (!$certificate || $certificate['validFrom_time_t'] > time() || $certificate['validTo_time_t'] <= time()) {
                $this->invalid();
            }
            $serials[] = ltrim(strtolower($certificate['serialNumberHex']), '0') ?: '0';
            $expires = min($expires, $certificate['validTo_time_t']);
            if ($i === 0) {
                if (!hash_equals($keyId, hash('sha256', $der))) {
                    $this->invalid();
                }
                $extension = $certificate['extensions'][self::OID] ?? null;
            } elseif (isset($certificate['extensions'][self::OID])) {
                // The first attestation extension seen from the trusted root must certify the signing leaf.
                $this->invalid();
            }
            $certificates[] = $pem;
        }
        $rootFile = config('hris.mobile_app.gps_root_file', resource_path('security/android-attestation-roots.pem'));
        $roots = @file_get_contents($rootFile);
        if (!$roots || strpos($roots, trim(end($certificates))) === false) {
            $this->invalid();
        }
        foreach (array_slice($certificates, 1) as $i => $issuer) {
            if (openssl_x509_verify($certificates[$i], $issuer) !== 1) {
                $this->invalid();
            }
        }
        $intermediate = tempnam(storage_path('framework/cache/data'), 'gps-cert-');
        if (!$intermediate) {
            NativeGpsProof::reject('Verifikasi sertifikat sedang tidak tersedia. Coba lagi atau hubungi admin.');
        }
        try {
            $middle = array_slice($certificates, 1, -1);
            if (file_put_contents($intermediate, implode('', $middle)) === false) {
                $this->invalid();
            }
            $valid = $middle
                ? openssl_x509_checkpurpose($certificates[0], X509_PURPOSE_ANY, [$rootFile], $intermediate)
                : openssl_x509_checkpurpose($certificates[0], X509_PURPOSE_ANY, [$rootFile]);
            if ($valid !== true && $valid !== 1) {
                $this->invalid();
            }
        } finally {
            unlink($intermediate);
        }
        $this->checkDescription($extension, $nonce);
        $pub = openssl_pkey_get_public($certificates[0]);
        $details = $pub ? openssl_pkey_get_details($pub) : false;
        if (!$details || ($details['ec']['curve_name'] ?? '') !== 'prime256v1') {
            $this->invalid();
        }
        return ['id' => $keyId, 'pem' => $details['key'], 'serials' => $serials, 'expires' => $expires];
    }

    private function sequence($der): array
    {
        if (!is_string($der)) {
            $this->invalid();
        }
        $decoded = ASN1::decodeBER($der);
        if (!$decoded || count($decoded) !== 1 || $decoded[0]['type'] !== ASN1::TYPE_SEQUENCE
            || $decoded[0]['length'] !== strlen($der)) {
            $this->invalid();
        }
        return $decoded[0]['content'];
    }

    private function authorization(array $list, int $tag)
    {
        foreach ($list as $item) {
            if (($item['constant'] ?? null) === $tag && $item['type'] === ASN1::CLASS_CONTEXT_SPECIFIC) {
                return $item['content'][0]['content'] ?? null;
            }
        }
        return null;
    }

    private function checkDescription($extension, string $nonce): void
    {
        $description = $this->sequence($extension);
        if (count($description) !== 8 || !in_array((string) $description[1]['content'], ['1', '2'], true)
            || !in_array((string) $description[3]['content'], ['1', '2'], true)
            || $description[4]['content'] !== hex2bin($nonce)) {
            $this->invalid();
        }
        $software = $description[6]['content'];
        $hardware = $description[7]['content'];
        foreach (array_merge($software, $hardware) as $item) {
            if (($item['constant'] ?? null) === 600) $this->invalid();
        }
        $purposes = $this->authorization($hardware, 1);
        $hasSigningPurpose = false;
        foreach (is_array($purposes) ? $purposes : [] as $purpose) {
            $hasSigningPurpose = $hasSigningPurpose || (string) $purpose['content'] === '2';
        }
        $boot = $this->authorization($hardware, 704);
        if (!$hasSigningPurpose || !is_array($boot) || ($boot[1]['content'] ?? null) !== true || (string) ($boot[2]['content'] ?? '') !== '0'
            || (string) $this->authorization($hardware, 702) !== '0') {
            $this->invalid();
        }
        $app = $this->sequence($this->authorization($software, 709) ?? $this->authorization($hardware, 709));
        $packages = $app[0]['content'] ?? [];
        $signers = $app[1]['content'] ?? [];
        if (count($packages) !== 1 || count($signers) !== 1
            || ($packages[0]['content'][0]['content'] ?? '') !== 'com.vdni.vpeople'
            || (int) (string) ($packages[0]['content'][1]['content'] ?? '0') < 7
            || !hash_equals((string) config('hris.mobile_app.gps_signer_sha256'), bin2hex($signers[0]['content']))) {
            $this->invalid();
        }
    }

    public function checkRevocation(array $serials): void
    {
        $cacheKey = 'gps:android:revocations:v1';
        $denied = Cache::get($cacheKey);
        if ($denied === null) {
            $unavailable = 'Status keamanan perangkat belum dapat diperiksa. Silakan coba lagi atau hubungi HR.';
            if (Cache::has($cacheKey . ':unavailable')) {
                NativeGpsProof::reject($unavailable);
            }
            try {
                $denied = Cache::lock($cacheKey . ':refresh', 8)->block(2, function () use ($cacheKey) {
                    $current = Cache::get($cacheKey);
                    if ($current !== null) {
                        return $current;
                    }
                    $response = Http::acceptJson()->connectTimeout(2)->timeout(5)->withOptions(['allow_redirects' => false])->get(self::STATUS_URL);
                    $entries = $response->json('entries');
                    if (!$response->ok() || !is_array($entries)) {
                        throw new \RuntimeException('Invalid attestation status list');
                    }
                    $revoked = [];
                    foreach (array_keys($entries) as $serial) {
                        $revoked[ltrim(strtolower((string) $serial), '0') ?: '0'] = true;
                    }
                    preg_match('/\bmax-age=(\d+)/i', $response->header('Cache-Control') ?? '', $match);
                    $ttl = min(3600, (int) ($match[1] ?? 0) - (int) $response->header('Age'));
                    if ($ttl > 0) {
                        Cache::put($cacheKey, $revoked, $ttl);
                    }
                    return $revoked;
                });
            } catch (\Exception $exception) {
                if (Cache::add($cacheKey . ':unavailable', true, 15)) {
                    \Illuminate\Support\Facades\Log::warning('Daftar pencabutan sertifikat GPS Android belum tersedia.', ['error_type' => get_class($exception)]);
                }
                NativeGpsProof::reject($unavailable);
            }
        }
        foreach ($serials as $serial) {
            if (isset($denied[$serial])) {
                $this->invalid();
            }
        }
    }
}
