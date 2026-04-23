<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $user = User::with([
            'role',
            'employee.departemen',
            'employee.divisi.departemen',
        ])->where('id', auth()->user()->id)->first();

        return view('user.dashboard', compact('user'));
    }
}
