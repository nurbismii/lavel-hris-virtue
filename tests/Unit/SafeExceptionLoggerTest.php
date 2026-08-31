<?php

namespace Tests\Unit;

use App\Services\Audit\AuditTrailService;
use App\Support\SafeExceptionLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class SafeExceptionLoggerTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');

        parent::tearDown();
    }

    public function test_safe_logger_keeps_action_class_and_context_id_without_exception_details(): void
    {
        $sensitiveMessage = 'NIK 7402243101930099 C:/private/roster.xlsx select * from users';

        Log::shouldReceive('warning')
            ->once()
            ->with('Sensitive workflow failure.', \Mockery::on(function (array $context) use ($sensitiveMessage): bool {
                $encoded = json_encode($context);

                $this->assertSame('roster.manual_submission', $context['action'] ?? null);
                $this->assertSame(RuntimeException::class, $context['exception_class'] ?? null);
                $this->assertMatchesRegularExpression(
                    '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                    $context['context_id'] ?? ''
                );
                $this->assertStringNotContainsString($sensitiveMessage, $encoded);
                $this->assertArrayNotHasKey('message', $context);
                $this->assertArrayNotHasKey('trace', $context);
                $this->assertArrayNotHasKey('bindings', $context);

                return true;
            }));

        $contextId = (new SafeExceptionLogger())->warning(
            'roster.manual_submission',
            new RuntimeException($sensitiveMessage)
        );

        $this->assertNotSame('', $contextId);
    }

    public function test_audit_trail_failure_uses_safe_logger_instead_of_raw_exception_message(): void
    {
        $logger = \Mockery::mock(SafeExceptionLogger::class);
        $logger->shouldReceive('warning')
            ->once()
            ->with('audit_trail.record', \Mockery::on(function (Throwable $exception): bool {
                return $exception instanceof Throwable;
            }))
            ->andReturn('4ebc14f9-cd37-4d3e-a26a-4534ac4ce50f');
        $this->app->instance(SafeExceptionLogger::class, $logger);

        $result = (new AuditTrailService())->record([
            'event' => 'roster_schedule.test',
            'module' => 'roster_schedule',
            'note' => 'NIK 7402243101930099 C:/private/roster.xlsx',
        ]);

        $this->assertNull($result);
    }
}
