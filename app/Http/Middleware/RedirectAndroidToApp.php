<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectAndroidToApp
{
    public function handle(Request $request, Closure $next)
    {
        $userAgent = $request->header('User-Agent') ?? '';

        $isAndroid = stripos($userAgent, 'Android') !== false;
        $isApp = stripos($userAgent, 'VPEOPLE_APP') !== false;

        if ($request->is('download-app')) {
            return $next($request);
        }

        if ($isAndroid && !$isApp) {
            Auth::logout();
            return redirect('/download-app');
        }

        if ($isApp) {
            return $next($request);
        }

        return $next($request);
    }
}
