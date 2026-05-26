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

    public function destroy(Request $request, string $id)
    {
        $notification = $request->user()
            ->notifications()
            ->whereKey($id)
            ->firstOrFail();

        $notification->delete();

        return $this->deleteResponse($request, 'Notifikasi berhasil dihapus.');
    }

    public function destroyRead(Request $request)
    {
        $deleted = $request->user()
            ->readNotifications()
            ->delete();

        $message = $deleted > 0
            ? $deleted . ' notifikasi yang sudah dibaca berhasil dihapus.'
            : 'Tidak ada notifikasi yang sudah dibaca untuk dihapus.';

        return $this->deleteResponse($request, $message, ['deleted' => $deleted]);
    }

    public function destroyAll(Request $request)
    {
        $deleted = $request->user()
            ->notifications()
            ->delete();

        $message = $deleted > 0
            ? $deleted . ' notifikasi berhasil dihapus.'
            : 'Tidak ada notifikasi untuk dihapus.';

        return $this->deleteResponse($request, $message, ['deleted' => $deleted]);
    }

    public function read(Request $request, string $id)
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        return redirect()->to($notification->data['url'] ?? route('kotak-masuk.index'));
    }

    private function deleteResponse(Request $request, string $message, array $data = [])
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $data,
            ]);
        }

        toast()->success('Berhasil', $message);

        return back();
    }
}
