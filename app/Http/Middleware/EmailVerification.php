<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailVerification
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            toast()->error('Verifikasi email kamu, cek folder kotak masuk/spam');
            return redirect()->route('login');
        }

        if (!Auth::user()->email_verified_at) {
            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
