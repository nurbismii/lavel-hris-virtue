<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\StatusPengajuanNotification;
use Tests\TestCase;

class StatusPengajuanNotificationTest extends TestCase
{
    public function test_status_notification_broadcast_does_not_force_sync_connection()
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'test-key',
            'broadcasting.connections.pusher.secret' => 'test-secret',
            'broadcasting.connections.pusher.app_id' => 'test-app',
        ]);

        $notification = new StatusPengajuanNotification([
            'judul' => 'Approval',
            'pesan' => 'Pengajuan menunggu approval.',
            'url' => '/kotak-masuk',
            'tipe' => 'Approval',
        ]);

        $this->assertContains('database', $notification->via(new User()));
        $this->assertContains('broadcast', $notification->via(new User()));
        $this->assertNull($notification->toBroadcast(new User())->connection);
    }
}
