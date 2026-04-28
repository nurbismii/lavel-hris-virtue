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
        $url = env('PRESENSI_FACE_VERIFICATION_URL', 'https://face-verifier.vdnisite.com/verify');
        $token = env('PRESENSI_FACE_VERIFICATION_TOKEN');

        $referencePath = public_path('/face-reference/230337694/reference_20260412132109.jpeg');
        $selfiePath = public_path('/face-reference/230337694/reference_20260412132109.jpeg');

        $this->info('URL: ' . $url);
        $this->info('Token: ' . ($token ? 'ADA' : 'KOSONG'));
        $this->info('Reference exists: ' . (file_exists($referencePath) ? 'YES' : 'NO'));
        $this->info('Selfie exists: ' . (file_exists($selfiePath) ? 'YES' : 'NO'));

        if (!$token) {
            $this->error('Token kosong.');
            return 1;
        }

        if (!file_exists($referencePath) || !file_exists($selfiePath)) {
            $this->error('File foto tidak ditemukan.');
            return 1;
        }

        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'X-Verify-Token' => $token,
                ])
                ->attach(
                    'reference_image',
                    file_get_contents($referencePath),
                    basename($referencePath)
                )
                ->attach(
                    'selfie_image',
                    file_get_contents($selfiePath),
                    basename($selfiePath)
                )
                ->post($url, [
                    'absensi_id' => 70,
                    'nik_karyawan' => '230337694',
                    'tanggal' => now()->toDateString(),
                    'presensi_challenge_id' => 'test-laravel',
                ]);

            $this->info('HTTP Status: ' . $response->status());
            $this->line('Body:');
            $this->line($response->body());

            return 0;
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}