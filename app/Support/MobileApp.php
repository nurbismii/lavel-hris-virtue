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

    public static function downloadUrl(string $platform): ?string
    {
        $url = config('hris.mobile_app.' . $platform . '_download_url');

        if (!$url && $platform === 'android') {
            $url = config('hris.mobile_app.download_url');
            if (!$url && is_file(public_path('downloads/vpeople-android.apk'))) {
                return url('/downloads/vpeople-android.apk');
            }
        }

        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL)
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https' ? $url : null;
    }
}
