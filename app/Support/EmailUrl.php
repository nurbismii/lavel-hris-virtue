<?php

namespace App\Support;

use Illuminate\Support\Facades\Request;

class EmailUrl
{
    public static function route(string $name, array $parameters = [], ?string $baseUrl = null): string
    {
        return self::absoluteUrl(route($name, $parameters, false), $baseUrl);
    }

    public static function login(?string $redirectUrl = null, ?string $baseUrl = null): string
    {
        $parameters = [];
        $redirect = self::safeRedirectPath($redirectUrl, $baseUrl);

        if ($redirect) {
            $parameters['redirect'] = $redirect;
        }

        return self::route('login', $parameters, $baseUrl);
    }

    public static function safeRedirectPath(?string $url, ?string $baseUrl = null): ?string
    {
        $url = trim((string) $url);

        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return null;
        }

        if (strpos($url, '//') === 0 || strpos($url, '\\') === 0) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return null;
        }

        if (isset($parts['scheme']) || isset($parts['host'])) {
            if (!self::isAllowedAbsoluteUrl($parts, $baseUrl)) {
                return null;
            }
        }

        $path = $parts['path'] ?? '/';

        if ($path === '' || $path[0] !== '/') {
            $path = '/' . ltrim($path, '/');
        }

        if ($path === '/login') {
            return null;
        }

        $query = isset($parts['query']) && $parts['query'] !== ''
            ? '?' . $parts['query']
            : '';

        return $path . $query;
    }

    public static function currentBaseUrl(): string
    {
        return self::baseUrl();
    }

    public static function baseUrl(?string $preferred = null): string
    {
        $candidates = [
            $preferred,
            config('hris.email.base_url'),
            self::requestRoot(),
            config('app.url'),
        ];

        $fallback = null;

        foreach ($candidates as $candidate) {
            $normalized = self::normalizeBaseUrl($candidate);

            if (!$normalized) {
                continue;
            }

            if ($fallback === null) {
                $fallback = $normalized;
            }

            if (!self::isLocalHost($normalized)) {
                return $normalized;
            }
        }

        return $fallback ?: 'http://localhost';
    }

    private static function absoluteUrl(string $path, ?string $baseUrl = null): string
    {
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        return rtrim(self::baseUrl($baseUrl), '/') . '/' . ltrim($path, '/');
    }

    private static function requestRoot(): ?string
    {
        if (!app()->bound('request')) {
            return null;
        }

        try {
            return Request::root();
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private static function normalizeBaseUrl($url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return strtolower($parts['scheme']) . '://' . $parts['host'] . $port;
    }

    private static function isAllowedAbsoluteUrl(array $parts, ?string $baseUrl = null): bool
    {
        $scheme = strtolower($parts['scheme'] ?? '');

        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');

        if ($host === '') {
            return false;
        }

        return self::hostSignature($parts) === self::hostSignature(parse_url(self::baseUrl($baseUrl)) ?: [])
            || self::isLocalHostParts($parts);
    }

    private static function hostSignature(array $parts): string
    {
        $host = strtolower($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $host . $port;
    }

    private static function isLocalHost(string $url): bool
    {
        $parts = parse_url($url);

        return self::isLocalHostParts($parts ?: []);
    }

    private static function isLocalHostParts(array $parts): bool
    {
        $host = strtolower($parts['host'] ?? '');

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}
