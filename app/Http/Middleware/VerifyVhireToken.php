<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyVhireToken
{
    public function handle(Request $request, Closure $next)
    {
        $expectedToken = (string) config('services.vhire.inbound_token');

        if ($expectedToken === '') {
            return response()->json([
                'success' => false,
                'message' => 'Konfigurasi token inbound V-Hire belum tersedia.',
            ], 503);
        }

        $providedToken = (string) $request->bearerToken();

        if ($providedToken === '') {
            $providedToken = (string) $request->header('X-Api-Key');
        }

        if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Token integrasi V-Hire tidak valid.',
            ], 401);
        }

        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
