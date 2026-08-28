<?php

namespace Tests\Feature;

use App\Console\Kernel;
use App\Models\ImportHistory;
use App\Services\Audit\AuditTrailService;
use App\Services\Storage\SensitiveFileStorageService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\CreatesRosterImportSchema;
use Tests\TestCase;

class CleanupExpiredRosterImportsTest extends TestCase
{
    use CreatesRosterImportSchema;

    private $storage;
    private $audit;
    private array $publicFixturePaths = [];

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
        $this->createRosterImportSchema();
        $this->createAuditSchema();
        $this->storage = new class extends SensitiveFileStorageService {
            public array $files = [];
            public array $deleted = [];
            public array $failures = [];

            public function resolvePath(string $relativePath, array $allowedPrefixes): ?string
            {
                return array_key_exists($relativePath, $this->files) ? 'memory://' . $relativePath : null;
            }

            public function resolvePrivatePath(string $relativePath, array $allowedPrefixes): ?string
            {
                return $this->resolvePath($relativePath, $allowedPrefixes);
            }

            public function delete(string $relativePath, array $allowedPrefixes): void
            {
                $this->deleted[] = $relativePath;

                if (isset($this->failures[$relativePath])) {
                    throw new RuntimeException('C:/private/' . $relativePath . ' KTP 7402243101930001');
                }

                unset($this->files[$relativePath]);
            }

            public function deletePrivate(string $relativePath, array $allowedPrefixes): void
            {
                $this->delete($relativePath, $allowedPrefixes);
            }
        };
        $this->audit = new class extends AuditTrailService {
            public array $records = [];
            public bool $throwOnRecord = false;

            public function record(array $data): ?\App\Models\AuditTrail
            {
                if ($this->throwOnRecord) {
                    throw new RuntimeException('Audit database unavailable.');
                }

                $this->records[] = $data;

                return null;
            }
        };
        $this->app->instance(SensitiveFileStorageService::class, $this->storage);
        $this->app->instance(AuditTrailService::class, $this->audit);
    }

    protected function tearDown(): void
    {
        foreach ($this->publicFixturePaths as $path) {
            File::delete($path);
        }

        $this->cleanRosterImportFixtures();
        Schema::dropAllTables();
        DB::disconnect('sqlite');

        parent::tearDown();
    }

    public function test_expired_source_and_failure_files_are_deleted_and_paths_cleared(): void
    {
        $history = $this->history('both', [
            'file_path' => 'roster-imports/both/source.xlsx',
            'failure_file_path' => 'roster-imports/both/failures.xlsx',
        ]);
        $this->storage->files = [
            $history->file_path => true,
            $history->failure_file_path => true,
        ];

        $this->runCleanup()->assertExitCode(0);

        $fresh = $history->fresh();
        $this->assertNull($fresh->file_path);
        $this->assertNull($fresh->failure_file_path);
        $this->assertSame(ImportHistory::STATUS_EXPIRED, $fresh->status);
        $this->assertSame(2, count($this->storage->deleted));
    }

    public function test_awaiting_and_validation_failed_statuses_transition_to_expired(): void
    {
        $awaiting = $this->history('awaiting', ['status' => ImportHistory::STATUS_AWAITING_CONFIRMATION, 'file_path' => 'roster-imports/awaiting/source.xlsx']);
        $validation = $this->history('validation', ['status' => ImportHistory::STATUS_VALIDATION_FAILED, 'file_path' => 'roster-imports/validation/source.xlsx']);

        foreach ([$awaiting, $validation] as $history) {
            $this->storage->files[$history->file_path] = true;
        }

        $this->runCleanup()->assertExitCode(0);

        $this->assertSame(ImportHistory::STATUS_EXPIRED, $awaiting->fresh()->status);
        $this->assertSame(ImportHistory::STATUS_EXPIRED, $validation->fresh()->status);
    }

    public function test_completed_and_failed_histories_retain_status_while_files_expire(): void
    {
        $completed = $this->history('completed', ['status' => ImportHistory::STATUS_COMPLETED, 'file_path' => 'roster-imports/completed/source.xlsx']);
        $failed = $this->history('failed', ['status' => ImportHistory::STATUS_FAILED, 'file_path' => 'roster-imports/failed/source.xlsx']);
        foreach ([$completed, $failed] as $history) {
            $this->storage->files[$history->file_path] = true;
        }

        $this->runCleanup()->assertExitCode(0);

        $this->assertSame(ImportHistory::STATUS_COMPLETED, $completed->fresh()->status);
        $this->assertSame(ImportHistory::STATUS_FAILED, $failed->fresh()->status);
        $this->assertNull($completed->fresh()->file_path);
        $this->assertNull($failed->fresh()->file_path);
    }

    public function test_unexpired_history_is_untouched(): void
    {
        $history = $this->history('future', [
            'expires_at' => now()->addHour(),
            'file_path' => 'roster-imports/future/source.xlsx',
        ]);
        $this->storage->files[$history->file_path] = true;

        $this->runCleanup()->assertExitCode(0);

        $this->assertSame('roster-imports/future/source.xlsx', $history->fresh()->file_path);
        $this->assertSame(ImportHistory::STATUS_AWAITING_CONFIRMATION, $history->fresh()->status);
        $this->assertSame([], $this->storage->deleted);
    }

    public function test_missing_file_path_is_idempotently_cleared(): void
    {
        $history = $this->history('missing', ['file_path' => 'roster-imports/missing/source.xlsx']);

        $this->runCleanup()->assertExitCode(0);
        $this->assertNull($history->fresh()->file_path);
        $this->assertCount(1, $this->audit->records);
    }

    public function test_repeated_runs_are_harmless_and_do_not_duplicate_audit_side_effects(): void
    {
        $history = $this->history('repeat', ['file_path' => 'roster-imports/repeat/source.xlsx']);
        $this->storage->files[$history->file_path] = true;

        $this->runCleanup()->assertExitCode(0);
        $this->runCleanup()->assertExitCode(0);
        $this->assertCount(1, $this->audit->records);
    }

    public function test_traversal_path_is_not_deleted_or_cleared_and_is_safely_logged(): void
    {
        Log::spy();
        $history = $this->history('traversal', ['file_path' => '../outside.xlsx']);
        $this->storage->files['../outside.xlsx'] = true;

        $this->runCleanup()->assertExitCode(1);

        $this->assertSame('../outside.xlsx', $history->fresh()->file_path);
        $this->assertSame([], $this->storage->deleted);
        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) use ($history): bool {
            return $message === 'Roster import cleanup failed.'
                && ($context['code'] ?? null) === 'roster_import_cleanup_failed'
                && ($context['import_id'] ?? null) === $history->import_id
                && isset($context['exception_class'])
                && strpos(json_encode($context), '../outside.xlsx') === false;
        });
    }

    public function test_limit_is_bounded_and_a_later_run_continues_remaining_records(): void
    {
        $first = $this->history('limit-1', ['file_path' => 'roster-imports/limit-1/source.xlsx']);
        $second = $this->history('limit-2', ['file_path' => 'roster-imports/limit-2/source.xlsx']);
        $third = $this->history('limit-3', ['file_path' => 'roster-imports/limit-3/source.xlsx']);
        foreach ([$first, $second, $third] as $history) {
            $this->storage->files[$history->file_path] = true;
        }

        $this->runCleanup(['--limit' => 2])->assertExitCode(0);
        $this->assertNull($first->fresh()->file_path);
        $this->assertNull($second->fresh()->file_path);
        $this->assertNotNull($third->fresh()->file_path);

        $this->runCleanup(['--limit' => 2])->assertExitCode(0);
        $this->assertNull($third->fresh()->file_path);
    }

    public function test_one_delete_failure_continues_and_never_clears_the_failed_path(): void
    {
        $broken = $this->history('broken', ['file_path' => 'roster-imports/broken/source.xlsx']);
        $healthy = $this->history('healthy', ['file_path' => 'roster-imports/healthy/source.xlsx']);
        $this->storage->files = [$broken->file_path => true, $healthy->file_path => true];
        $this->storage->failures[$broken->file_path] = true;

        $this->runCleanup()->assertExitCode(1);

        $this->assertSame('roster-imports/broken/source.xlsx', $broken->fresh()->file_path);
        $this->assertNull($healthy->fresh()->file_path);
    }

    public function test_public_file_with_same_relative_path_is_never_deleted(): void
    {
        $relativePath = 'roster-imports/cleanup-public-boundary/source.xlsx';
        $publicPath = public_path($relativePath);
        File::ensureDirectoryExists(dirname($publicPath));
        File::put($publicPath, 'public-file-must-remain');
        $this->publicFixturePaths[] = $publicPath;
        $history = $this->history('public-boundary', ['file_path' => $relativePath]);
        $this->app->instance(SensitiveFileStorageService::class, new SensitiveFileStorageService());

        $this->runCleanup()->assertExitCode(0);

        $this->assertTrue(File::isFile($publicPath));
        $this->assertSame('public-file-must-remain', File::get($publicPath));
        $this->assertNull($history->fresh()->file_path);
    }

    public function test_audit_failure_returns_failure_but_continues_remaining_records(): void
    {
        Log::spy();
        $first = $this->history('audit-failure-1', ['file_path' => 'roster-imports/audit-failure-1/source.xlsx']);
        $second = $this->history('audit-failure-2', ['file_path' => 'roster-imports/audit-failure-2/source.xlsx']);
        $this->storage->files = [$first->file_path => true, $second->file_path => true];
        $this->audit->throwOnRecord = true;

        $this->runCleanup()->assertExitCode(1);

        $this->assertNull($first->fresh()->file_path);
        $this->assertNull($second->fresh()->file_path);
        Log::shouldHaveReceived('warning')->twice()->withArgs(function (string $message, array $context): bool {
            return $message === 'Roster import cleanup audit failed.'
                && ($context['code'] ?? null) === 'roster_import_cleanup_audit_failed'
                && isset($context['import_id'], $context['exception_class'])
                && !isset($context['path']);
        });
    }

    public function test_audit_metadata_is_aggregate_only_and_excludes_sensitive_values(): void
    {
        $history = $this->history('safe-audit', ['file_path' => 'roster-imports/safe-audit/source.xlsx']);
        $this->storage->files[$history->file_path] = true;

        $this->runCleanup()->assertExitCode(0);

        $record = $this->audit->records[0];
        $this->assertSame('roster_schedule_import.cleaned', $record['event']);
        $this->assertSame('system', $record['actor_id']);
        $this->assertSame([
            'import_id', 'previous_status', 'final_status', 'deleted_file_count',
        ], array_keys($record['metadata']));
        $encoded = json_encode($record);
        $this->assertStringNotContainsString('roster-imports/', $encoded);
        $this->assertStringNotContainsString('740224310193', $encoded);
    }

    public function test_scheduler_registers_single_hourly_non_overlapping_cleanup_command(): void
    {
        $schedule = new Schedule();
        $method = new \ReflectionMethod(Kernel::class, 'schedule');
        $method->setAccessible(true);
        $method->invoke(app(Kernel::class), $schedule);
        $events = collect($schedule->events())
            ->filter(fn ($event): bool => strpos($event->command, 'roster:cleanup-expired-imports --limit=500') !== false)
            ->values();

        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
    }

    private function runCleanup(array $options = [])
    {
        return $this->artisan('roster:cleanup-expired-imports', $options);
    }

    private function history(string $importId, array $overrides = []): ImportHistory
    {
        return ImportHistory::create(array_merge([
            'import_id' => $importId,
            'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_AWAITING_CONFIRMATION,
            'expires_at' => now()->subMinute(),
        ], $overrides));
    }

    private function createAuditSchema(): void
    {
        Schema::create('audit_trails', function (Blueprint $table): void {
            $table->id();
            $table->string('event')->nullable();
            $table->string('module')->nullable();
            $table->string('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_role')->nullable();
            $table->string('reference_table')->nullable();
            $table->string('reference_id')->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamps();
        });
    }
}
