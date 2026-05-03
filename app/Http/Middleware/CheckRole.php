<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    public function handle($request, Closure $next, ...$roles)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user()->loadMissing('role');

        if (!$user->hasAnyRole()) {
            abort(403, 'Role tidak ditemukan.');
        }

        if ($user->hasRole($roles)) {
            return $next($request);
        }

        abort(403, 'Akses tidak diizinkan.');
    }
}
