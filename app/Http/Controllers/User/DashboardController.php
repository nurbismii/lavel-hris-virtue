<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = User::with('employee')->where('id', auth()->user()->id)->first();

        // Update terakhir_login (optional tapi recommended)
        if ($user->terakhir_login == null) {
            $user->terakhir_login = now();
            $user->save();
        }

        return view('user.dashboard', compact('user'));
    }
}
