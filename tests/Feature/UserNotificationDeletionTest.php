<?php

namespace Tests\Feature;

use App\Http\Controllers\User\NotificationController;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserNotificationDeletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->string('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_user_can_delete_only_their_own_notification(): void
    {
        $user = $this->makeUser('user-1');
        $ownNotificationId = $this->createNotification('user-1');
        $otherNotificationId = $this->createNotification('user-2');

        $response = app(NotificationController::class)->destroy(
            $this->jsonRequest($user),
            $ownNotificationId
        );

        $this->assertTrue($response->getData(true)['success']);
        $this->assertDatabaseMissing('notifications', ['id' => $ownNotificationId]);
        $this->assertDatabaseHas('notifications', ['id' => $otherNotificationId]);
    }

    public function test_user_cannot_delete_another_users_notification(): void
    {
        $user = $this->makeUser('user-1');
        $otherNotificationId = $this->createNotification('user-2');

        $this->expectException(ModelNotFoundException::class);

        app(NotificationController::class)->destroy(
            $this->jsonRequest($user),
            $otherNotificationId
        );
    }

    public function test_user_can_delete_only_their_read_notifications(): void
    {
        $user = $this->makeUser('user-1');
        $ownReadId = $this->createNotification('user-1', now());
        $ownUnreadId = $this->createNotification('user-1');
        $otherReadId = $this->createNotification('user-2', now());

        $response = app(NotificationController::class)->destroyRead($this->jsonRequest($user));

        $this->assertSame(1, $response->getData(true)['data']['deleted']);
        $this->assertDatabaseMissing('notifications', ['id' => $ownReadId]);
        $this->assertDatabaseHas('notifications', ['id' => $ownUnreadId]);
        $this->assertDatabaseHas('notifications', ['id' => $otherReadId]);
    }

    public function test_user_can_delete_all_only_for_their_account(): void
    {
        $user = $this->makeUser('user-1');
        $ownReadId = $this->createNotification('user-1', now());
        $ownUnreadId = $this->createNotification('user-1');
        $otherNotificationId = $this->createNotification('user-2');

        $response = app(NotificationController::class)->destroyAll($this->jsonRequest($user));

        $this->assertSame(2, $response->getData(true)['data']['deleted']);
        $this->assertDatabaseMissing('notifications', ['id' => $ownReadId]);
        $this->assertDatabaseMissing('notifications', ['id' => $ownUnreadId]);
        $this->assertDatabaseHas('notifications', ['id' => $otherNotificationId]);
    }

    private function makeUser(string $id): User
    {
        $user = new User();
        $user->id = $id;
        $user->name = 'User ' . $id;
        $user->email = $id . '@example.test';

        return $user;
    }

    private function jsonRequest(User $user): Request
    {
        $request = Request::create('/notif', 'DELETE');
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn($guard = null) => $user);

        return $request;
    }

    private function createNotification(string $userId, $readAt = null): string
    {
        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\\Notifications\\StatusPengajuanNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $userId,
            'data' => json_encode([
                'judul' => 'Notifikasi',
                'pesan' => 'Pesan notifikasi.',
                'url' => '/kotak-masuk',
                'tipe' => 'Sistem',
            ]),
            'read_at' => $readAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
