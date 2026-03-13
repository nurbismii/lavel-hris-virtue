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

        // Route yang dikecualikan
        if ($request->routeIs([
            'login',
            'register',
            'password.*'
        ]) || $request->is('download-app')) {
            return $next($request);
        }

        if ($isAndroid && !$isApp) {
            Auth::logout();
            return redirect('/download-app');
        }

        return $next($request);
    }
}
