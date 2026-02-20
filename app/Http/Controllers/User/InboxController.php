<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;

class InboxController extends Controller
{
    public function index()
    {
        $notifications = auth()->user()
            ->notifications()
            ->latest()
            ->paginate(10);

        return view('user.inbox.index', compact('notifications'));
    }
}
