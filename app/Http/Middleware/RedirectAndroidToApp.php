<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\MobileApp;

class RedirectAndroidToApp
{
    public function handle(Request $request, Closure $next)
    {
        if (!MobileApp::requiresApp($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'code' => 'mobile_app_required',
                'message' => 'Silakan gunakan aplikasi V-People untuk masuk dari ponsel atau tablet.',
                'data' => ['download_url' => route('mobile.download')],
            ], 403)->header('Cache-Control', 'no-store, private');
        }

        return redirect()->route('mobile.download')->header('Cache-Control', 'no-store, private');
    }
}
