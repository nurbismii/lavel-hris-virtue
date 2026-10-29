<?php

namespace Tests\Feature;

use App\Console\Kernel;
use App\Services\Presensi\ServerSideFaceVerificationService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class FaceProviderConnectionTest extends TestCase
{
    private $connections = [];
    private $databaseFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
    }

    protected function tearDown(): void
    {
        foreach ($this->connections as $connection) {
            $connection->disconnect();
        }
        foreach ($this->databaseFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    private function connection(string $driver, bool $memory = false): Connection
    {
        $path = $memory ? ':memory:' : tempnam(sys_get_temp_dir(), 'hris-face-');
        if (!$memory) {
            $this->databaseFiles[] = $path;
        }
        $connection = new Connection(new PDO('sqlite:' . $path), $path, '', ['driver' => $driver]);
        $connection->setReconnector(function (Connection $connection) use ($path) {
            $connection->setPdo(new PDO('sqlite:' . $path));
        });
        $connection->statement('CREATE TABLE verification_results (id INTEGER PRIMARY KEY)');
        $connection->insert('INSERT INTO verification_results (id) VALUES (1)');
        $this->connections[] = $connection;
        return $connection;
    }

    private function disconnectForProvider(Connection $connection): void
    {
        DB::shouldReceive('connection')->once()->andReturn($connection);
        $method = new \ReflectionMethod(ServerSideFaceVerificationService::class, 'disconnectIdleDatabase');
        $method->setAccessible(true);
        $method->invoke(app(ServerSideFaceVerificationService::class));
    }

    public function test_idle_mysql_slot_is_released_and_result_writes_can_reconnect(): void
    {
        $connection = $this->connection('mysql');
        $this->disconnectForProvider($connection);
        $this->assertNull($connection->getRawPdo());

        $connection->transaction(function () use ($connection) {
            $connection->insert('INSERT INTO verification_results (id) VALUES (2)');
        });
        $this->assertSame(2, (int) $connection->selectOne('SELECT COUNT(*) AS total FROM verification_results')->total);
    }

    public function test_an_active_transaction_is_preserved_and_can_still_roll_back(): void
    {
        $connection = $this->connection('mysql');
        $connection->beginTransaction();
        $connection->insert('INSERT INTO verification_results (id) VALUES (2)');
        $this->disconnectForProvider($connection);

        $this->assertSame(1, $connection->transactionLevel());
        $this->assertInstanceOf(PDO::class, $connection->getRawPdo());
        $connection->rollBack();
        $this->assertSame(1, (int) $connection->selectOne('SELECT COUNT(*) AS total FROM verification_results')->total);
    }

    public function test_sqlite_memory_database_survives_provider_preparation(): void
    {
        $connection = $this->connection('sqlite', true);
        $this->disconnectForProvider($connection);
        $this->assertInstanceOf(PDO::class, $connection->getRawPdo());
        $this->assertSame(1, (int) $connection->selectOne('SELECT COUNT(*) AS total FROM verification_results')->total);
    }

    public function test_resign_schedule_keeps_minutely_cadence_and_prevents_overlap(): void
    {
        $schedule = new Schedule();
        $method = new \ReflectionMethod(Kernel::class, 'schedule');
        $method->setAccessible(true);
        $method->invoke(app(Kernel::class), $schedule);
        $events = collect($schedule->events())->filter(function ($event) {
            return strpos($event->command, 'update.resign:cron') !== false;
        })->values();
        $this->assertCount(1, $events);
        $this->assertSame('* * * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
        $this->assertSame(30, $events->first()->expiresAt);
    }
}
