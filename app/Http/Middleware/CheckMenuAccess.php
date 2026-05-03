<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class CheckMenuAccess
{
    public function handle($request, Closure $next, ...$menus)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user()->loadMissing('role');

        if (!$user->hasAnyRole()) {
            abort(403, 'Role tidak ditemukan.');
        }

        if ($user->hasMenuAccess($menus)) {
            return $next($request);
        }

        abort(403, 'Akses menu tidak diizinkan.');
    }
}
