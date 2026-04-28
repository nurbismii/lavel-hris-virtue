<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestFaceVerifier extends Command
{
    protected $signature = 'face:test-verifier';
    protected $description = 'Test koneksi Laravel ke face verifier';

    public function handle()
    {
        $url = config('services.presensi_face.endpoint');
        $token = trim((string) config('services.presensi_face.token'));
        $timeout = (int) config('services.presensi_face.timeout', 60);

        $this->info('URL: ' . ($url ?: 'KOSONG'));
        $this->info('Token: ' . ($token ? 'ADA' : 'KOSONG'));
        $this->info('Token Length: ' . strlen($token));

        if (!$url) {
            $this->error('PRESENSI_FACE_VERIFICATION_URL belum terbaca.');
            return 1;
        }

        if (!$token) {
            $this->error('PRESENSI_FACE_VERIFICATION_TOKEN belum terbaca.');
            return 1;
        }

        $this->line('');
        $this->info('Testing token tanpa upload file...');

        $tokenTest = Http::timeout($timeout)
            ->withHeaders([
                'X-Verify-Token' => $token,
            ])
            ->post($url);

        $this->info('HTTP Status: ' . $tokenTest->status());
        $this->line('Body:');
        $this->line($tokenTest->body());

        return 0;
    }
}
