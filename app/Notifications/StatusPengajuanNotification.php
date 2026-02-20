<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
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
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'judul' => $this->data['judul'],
            'pesan' => $this->data['pesan'],
            'url'   => $this->data['url'],
            'tipe'  => $this->data['tipe'],
        ];
    }

    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
