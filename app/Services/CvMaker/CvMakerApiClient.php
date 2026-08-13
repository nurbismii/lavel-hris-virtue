<?php

namespace App\Services\CvMaker;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CvMakerApiClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.cv_maker.api_base_url'))
            && filled(config('services.cv_maker.api_token'));
    }

    public function profiles(array $hashes): array
    {
        $hashes = array_values(array_unique(array_filter($hashes)));

        if (!$this->isConfigured() || empty($hashes)) {
            return [];
        }

        try {
            $response = Http::withToken((string) config('services.cv_maker.api_token'))
                ->acceptJson()
                ->asJson()
                ->withOptions([
                    'connect_timeout' => (int) config('services.cv_maker.api_connect_timeout', 5),
                ])
                ->timeout((int) config('services.cv_maker.api_timeout', 15))
                ->retry(2, 200)
                ->post(rtrim((string) config('services.cv_maker.api_base_url'), '/') . '/api/internal/vpeople/cv-data', [
                    'hashes' => array_slice($hashes, 0, 100),
                ]);

            if (!$response->successful()) {
                throw new RuntimeException('CV Maker API merespons HTTP ' . $response->status() . '.');
            }

            $profiles = $response->json('data.profiles');

            if (!is_array($profiles)) {
                throw new RuntimeException('Format respons CV Maker API tidak valid.');
            }

            return collect($profiles)
                ->filter(function ($profile) {
                    return is_array($profile) && !empty($profile['vpeople_nik_hash']);
                })
                ->keyBy('vpeople_nik_hash')
                ->all();
        } catch (Throwable $exception) {
            Log::warning('CV Maker API lookup failed.', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'hash_count' => count($hashes),
            ]);

            return [];
        }
    }

    public function file(string $path, string $nikHash)
    {
        if (!$this->isConfigured() || !preg_match('/^[a-f0-9]{64}$/', $nikHash)) {
            return null;
        }

        try {
            $response = Http::withToken((string) config('services.cv_maker.api_token'))
                ->withOptions(['connect_timeout' => (int) config('services.cv_maker.api_connect_timeout', 5)])
                ->timeout(max(30, (int) config('services.cv_maker.api_timeout', 15)))
                ->get(rtrim((string) config('services.cv_maker.api_base_url'), '/') . '/' . ltrim($path, '/'), [
                    'hash' => $nikHash,
                ]);

            return $response->successful() ? $response : null;
        } catch (Throwable $exception) {
            Log::warning('CV Maker private file lookup failed.', [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
