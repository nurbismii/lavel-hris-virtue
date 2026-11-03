<?php

namespace App\Support;

use Illuminate\Http\Request;

class MobileApp
{
    public static function isNative(Request $request): bool
    {
        // shortcut: user-agent routing is spoofable; use device attestation if app authenticity becomes required.
        return (bool) preg_match('/(?:^|\s)VPEOPLE_APP(?:\/[\d.]+)?(?:\s|$)/i', (string) $request->userAgent());
    }

    public static function isMobileBrowser(Request $request): bool
    {
        if (self::isNative($request)) {
            return false;
        }

        return $request->header('Sec-CH-UA-Mobile') === '?1'
            || (bool) preg_match('/Android|iPhone|iPad|iPod|IEMobile|Windows Phone|BlackBerry|BB10|Opera Mini|Mobile/i', (string) $request->userAgent());
    }

    public static function requiresApp(Request $request): bool
    {
        if (!config('hris.mobile_app.required', true) || !self::isMobileBrowser($request)) {
            return false;
        }

        return !preg_match('/iPhone|iPad|iPod/i', (string) $request->userAgent())
            || config('hris.mobile_app.ios_required', false);
    }

    public static function allowsIosBrowserAttendance(Request $request): bool
    {
        if (!config('hris.mobile_app.ios_browser_attendance', false)
            || config('hris.mobile_app.ios_required', false)
            || self::isNative($request)
            || preg_match('/Android/i', (string) $request->userAgent())) {
            return false;
        }

        // shortcut: browser identity is spoofable; disable this exception after native iOS GPS verification is available.
        return (bool) preg_match('/iPhone|iPad|iPod/i', (string) $request->userAgent())
            || (preg_match('/Macintosh/i', (string) $request->userAgent()) && $request->input('ios_browser') === '1');
    }

    public static function downloadUrl(string $platform): ?string
    {
        $candidates = [config('hris.mobile_app.' . $platform . '_download_url')];
        if ($platform === 'android') {
            $candidates[] = config('hris.mobile_app.download_url');
        }
        $downloadPage = parse_url(route('mobile.download'));

        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }
            $candidate = trim($candidate);
            if (!filter_var($candidate, FILTER_VALIDATE_URL)) {
                continue;
            }
            $parts = parse_url($candidate);
            if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https') {
                continue;
            }
            // A link to this landing page would reload it instead of downloading the app.
            if (strcasecmp($parts['host'] ?? '', $downloadPage['host'] ?? '') === 0
                && ($parts['port'] ?? 443) === ($downloadPage['port'] ?? 443)
                && rtrim($parts['path'] ?? '', '/') === rtrim($downloadPage['path'] ?? '', '/')) {
                continue;
            }
            return $candidate;
        }

        return $platform === 'android' && is_file(public_path('downloads/vpeople-android.apk'))
            ? url('/downloads/vpeople-android.apk') : null;
    }
}
