<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    public function handle($request, Closure $next, ...$roles)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        if (!$user->role) {
            abort(403, 'Role tidak ditemukan.');
        }

        if (in_array($user->role->permission_role, $roles)) {
            return $next($request);
        }

        abort(403, 'Akses tidak diizinkan.');
    }
}
