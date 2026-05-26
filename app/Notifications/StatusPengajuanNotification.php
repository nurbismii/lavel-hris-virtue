<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class StatusPengajuanNotification extends Notification
{
    use Queueable;

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function via($notifiable)
    {
        $channels = ['database'];

        if ($this->shouldBroadcast()) {
            $channels[] = 'broadcast';
        }

        return $channels;
    }

    public function toDatabase($notifiable)
    {
        return $this->payload();
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage($this->payload());
    }

    public function toArray($notifiable)
    {
        return $this->payload();
    }

    private function payload(): array
    {
        return [
            'judul' => $this->data['judul'] ?? 'Notifikasi',
            'pesan' => $this->data['pesan'] ?? '-',
            'url' => $this->data['url'] ?? route('kotak-masuk.index'),
            'tipe' => $this->data['tipe'] ?? 'Sistem',
        ];
    }

    private function shouldBroadcast(): bool
    {
        return config('broadcasting.default') === 'pusher'
            && filled(config('broadcasting.connections.pusher.key'))
            && filled(config('broadcasting.connections.pusher.secret'))
            && filled(config('broadcasting.connections.pusher.app_id'));
    }
}
