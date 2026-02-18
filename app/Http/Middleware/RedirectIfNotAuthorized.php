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

        $user = Auth::user()->load('role');

        // Pastikan role ada
        if (!$user->role) {
            abort(403, 'Role tidak ditemukan.');
        }

        $roleName = $user->role->permission_role;

        // Jika role adalah User → paksa ke dashboard
        if ($roleName === 'User') {

            // Kalau dia sudah di dashboard, biarkan lanjut
            if ($request->is('dashboard')) {
                return $next($request);
            }

            return redirect('/dashboard');
        }

        // Role lain (Administrator, HRD, Manager, dll)
        return $next($request);
    }
}
