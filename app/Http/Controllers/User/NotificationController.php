<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function latest(Request $request)
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->data['judul'] ?? 'Notifikasi',
                    'message' => $notification->data['pesan'] ?? '-',
                    'type' => $notification->data['tipe'] ?? 'Sistem',
                    'url' => $notification->data['url'] ?? route('kotak-masuk.index'),
                    'read_url' => route('notif.baca', $notification->id),
                    'unread' => is_null($notification->read_at),
                    'created_at_human' => $notification->created_at->diffForHumans(),
                ];
            })
            ->values();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'items' => $notifications,
        ]);
    }

    public function readAll(Request $request)
    {
        $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return back();
    }

    public function read(Request $request, string $id)
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        return redirect()->to($notification->data['url'] ?? route('kotak-masuk.index'));
    }
}
