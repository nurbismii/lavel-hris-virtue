<?php

namespace App\Services\Recruitment;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class RecruitmentDocumentClient
{
    public function lookupByNoKtp(string $noKtp): array
    {
        $noKtp = trim($noKtp);

        if ($noKtp === '') {
            return [
                'found' => false,
                'candidate' => null,
                'documents' => [],
            ];
        }

        $cacheKey = 'recruitment_documents:' . sha1($noKtp);
        $ttl = max((int) config('services.recruitment.cache_ttl', 3), 0);

        if ($ttl === 0) {
            return $this->requestDocuments($noKtp);
        }

        return Cache::remember($cacheKey, now()->addMinutes($ttl), fn() => $this->requestDocuments($noKtp));
    }

    private function requestDocuments(string $noKtp): array
    {
        $baseUrl = rtrim((string) config('services.recruitment.base_url'), '/');
        $token = (string) config('services.recruitment.token');

        if ($baseUrl === '' || $token === '') {
            throw new RuntimeException('Konfigurasi API recruitment belum lengkap.');
        }

        $response = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->withToken($token)
            ->timeout((int) config('services.recruitment.timeout', 10))
            ->post('/api/internal/candidate-documents', [
                'no_ktp' => $noKtp,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('API recruitment gagal merespons. Status: ' . $response->status());
        }

        $payload = $response->json() ?: [];
        $documents = collect(data_get($payload, 'documents', []))
            ->map(fn($document) => $this->normalizeDocument($document, $baseUrl))
            ->filter(fn($document) => filled($document['preview_url']) || filled($document['download_url']))
            ->values()
            ->all();

        return [
            'found' => (bool) data_get($payload, 'found', false),
            'candidate' => data_get($payload, 'candidate'),
            'documents' => $documents,
        ];
    }

    private function normalizeDocument(array $document, string $baseUrl): array
    {
        $type = (string) data_get($document, 'type', '');
        $label = (string) data_get($document, 'label', '');

        if ($label === '') {
            $label = $type !== '' ? Str::headline($type) : 'Dokumen';
        }

        return [
            'type' => $type,
            'label' => $label,
            'mime' => (string) data_get($document, 'mime', 'application/octet-stream'),
            'preview_url' => $this->normalizeUrl((string) data_get($document, 'preview_url', ''), $baseUrl),
            'download_url' => $this->normalizeUrl((string) data_get($document, 'download_url', ''), $baseUrl),
            'expires_at' => data_get($document, 'expires_at'),
        ];
    }

    private function normalizeUrl(string $url, string $baseUrl): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return $baseUrl . '/' . ltrim($url, '/');
    }
}
