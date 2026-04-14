<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
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
