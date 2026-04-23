<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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

    protected function authenticated(Request $request, $user)
    {
        $user->markLastLogin();
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
