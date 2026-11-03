<?php

namespace App\Http\Middleware;

use App\Services\Presensi\AndroidGpsAttestation;
use App\Services\Presensi\NativeGpsProof;
use App\Support\MobileApp;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RequireNativeGpsProof
{
    public function handle(Request $request, Closure $next)
    {
        if (MobileApp::allowsIosBrowserAttendance($request)) {
            $request->attributes->set('gps_verification_method', 'ios_browser');
            return $next($request);
        }

        try {
            $purpose = $request->route('type') ?? 'log';
            $fix = app(NativeGpsProof::class)->verifiedFix($request, $purpose, app(AndroidGpsAttestation::class));
            $request->merge($fix);
            $request->attributes->set('gps_verification_method', 'android_hardware');
        } catch (ValidationException $error) {
            $message = $error->errors()['gps_proof'][0];
            if ($request->expectsJson() || !$request->route('type')) {
                return response()->json(['success' => false, 'message' => $message, 'errors' => $error->errors()], 422);
            }
            return redirect()->route('presensi.index')->withErrors(['gps_proof' => $message]);
        }
        return $next($request);
    }
}
