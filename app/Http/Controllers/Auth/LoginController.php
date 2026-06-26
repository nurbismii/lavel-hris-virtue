<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\EmailUrl;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function showLoginForm(Request $request)
    {
        $redirect = EmailUrl::safeRedirectPath($request->query('redirect'));

        if ($redirect) {
            $request->session()->put('url.intended', $redirect);
        }

        return view('auth.login', [
            'redirect' => $redirect,
        ]);
    }

    protected function validateLogin(Request $request)
    {
        $request->validate([
            $this->username() => 'required|string',
            'password' => 'required|string',
            'remember' => 'nullable|boolean',
        ]);
    }

    protected function attemptLogin(Request $request)
    {
        return $this->guard()->attempt(
            $this->credentials($request),
            $request->boolean('remember')
        );
    }

    protected function authenticated(Request $request, $user)
    {
        $user->markLastLogin();

        $redirect = EmailUrl::safeRedirectPath($request->input('redirect'));

        if ($redirect) {
            return redirect()->to($redirect);
        }
    }

    protected function redirectTo()
    {
        $user = auth()->user();

        if ($user && $user->hasMenuAccess('dashboard_admin')) {
            return '/admin/home';
        }

        if ($user && $user->hasMenuAccess('dashboard_karyawan')) {
            return '/dashboard';
        }

        return '/pengaturan-akun/update';
    }
}
