<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfNotAuthorized
{
    public function handle($request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user()->loadMissing('role');

        if (!$user->role) {
            abort(403, 'Role tidak ditemukan.');
        }

        if ($request->is('admin/home') && !$user->hasMenuAccess('dashboard_admin') && $user->hasMenuAccess('dashboard_karyawan')) {
            return redirect('/dashboard');
        }

        return $next($request);
    }
}
