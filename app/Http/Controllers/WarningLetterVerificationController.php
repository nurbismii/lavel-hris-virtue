<?php

namespace App\Http\Controllers;

use App\Services\SuratPeringatan\WarningLetterVerificationService;
use Illuminate\Http\Request;
use Throwable;

class WarningLetterVerificationController extends Controller
{
    public function show(Request $request, string $token, WarningLetterVerificationService $service)
    {
        $letter = $service->find(strtolower($token));
        $statusCode = $letter ? 200 : 404;
        $verification = $letter ? $service->publicData($letter) : ['status' => 'invalid'];

        if ($letter) {
            try {
                $service->recordAccess($letter, $request);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return response()->view('warning-letters.verify', compact('verification'), $statusCode)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('X-Frame-Options', 'DENY')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Content-Security-Policy', "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
    }
}
