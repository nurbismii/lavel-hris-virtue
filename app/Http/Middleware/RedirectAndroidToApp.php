<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RedirectAndroidToApp
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('download-app')) {
            return $next($request);
        }

        if ($request->header('X-APP') === 'V-PEOPLE') {
            return $next($request);
        }

        $userAgent = $request->header('User-Agent');

        if ($userAgent && stripos($userAgent, 'Android') !== false) {
            return redirect('/download-app');
        }

        return $next($request);
    }
}
