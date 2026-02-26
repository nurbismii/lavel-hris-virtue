<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    public function showLinkRequestForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $email = Str::lower($request->email);
        $key = 'password-reset:' . $email;

        // Maksimal 3x per hari (1440 menit)
        if (RateLimiter::tooManyAttempts($key, 3)) {

            $seconds = RateLimiter::availableIn($key);
            $hours = ceil($seconds / 3600);

            return back()->withErrors([
                'email' => "Anda sudah mencapai batas maksimal reset password hari ini. Coba lagi dalam {$hours} jam."
            ]);
        }

        RateLimiter::hit($key, 86400); // 86400 detik = 1 hari

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return back()->with('status', __($status));
    }
}
