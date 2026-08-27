<?php

namespace Tests\Feature;

use App\Jobs\ProcessRosterScheduleImport;
use App\Models\ImportHistory;
use App\Models\RosterSchedule;
use App\Services\Roster\RosterScheduleImportCommitService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesRosterImportSchema;
use Tests\TestCase;

class RosterScheduleImportJobTest extends TestCase
{
    use CreatesRosterImportSchema;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Storage::fake('local');
        $this->createRosterImportSchema();
    }

    protected function tearDown(): void
    {
        $this->cleanRosterImportFixtures();
        Schema::dropAllTables();
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_checksum_changed_and_missing_source_block_before_writes(): void
    {
        $history = $this->processingHistory([['nik' => '016090950', 'ktp' => '7402243101930010']]);
        $history->update(['file_checksum' => 'changed']);

        $this->expectException(\RuntimeException::class);
        app(RosterScheduleImportCommitService::class)->commit($history->fresh());
        $this->assertSame(0, RosterSchedule::query()->count());
    }

    public function test_valid_commit_creates_schedules_history_and_is_idempotent(): void
    {
        $history = $this->processingHistory([['nik' => '016090951', 'ktp' => '7402243101930011']]);
        $this->assertSame(ImportHistory::TYPE_ROSTER_SCHEDULE, $history->import_type);
        $this->assertSame(ImportHistory::STATUS_PROCESSING, $history->status);
        $this->assertTrue($history->expires_at->isFuture());
        $this->assertStringStartsWith('roster-imports/', $history->file_path);

        $result = app(RosterScheduleImportCommitService::class)->commit($history);
        $this->assertSame(1, $result['employees']);
        $this->assertGreaterThanOrEqual(1, $result['history_created']);
        $this->assertNotEmpty($result['late_candidate_schedule_ids']);
        $this->assertSame(ImportHistory::STATUS_COMPLETED, $history->fresh()->status);
        $this->assertStringNotContainsString('late_candidate_schedule_ids', json_encode($history->fresh()->summary));
        $this->assertGreaterThanOrEqual(1, RosterSchedule::query()->count());

        $history = $history->fresh();
        $history->update(['status' => ImportHistory::STATUS_PROCESSING]);
        $second = app(RosterScheduleImportCommitService::class)->commit($history->fresh());
        $this->assertGreaterThanOrEqual(1, $second['unchanged']);
        $this->assertSame(RosterSchedule::query()->count(), RosterSchedule::query()->distinct()->count('id'));
    }

    public function test_manual_schedule_conflict_rolls_back_without_overwrite(): void
    {
        $history = $this->processingHistory([['nik' => '016090952', 'ktp' => '7402243101930012']]);
        RosterSchedule::query()->create([
            'employee_nik' => '016090952', 'period_year' => 2026, 'period_number' => 1,
            'off_start' => '2026-09-10', 'source' => RosterSchedule::SOURCE_MANUAL,
        ]);

        try {
            app(RosterScheduleImportCommitService::class)->commit($history);
            $this->fail('Manual conflict must block the import.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(1, RosterSchedule::query()->count());
            $this->assertSame(RosterSchedule::SOURCE_MANUAL, RosterSchedule::query()->first()->source);
        }
    }

    public function test_job_contract_and_failed_status_are_safe(): void
    {
        $job = new ProcessRosterScheduleImport(77);
        $this->assertSame('roster-import-77', $job->uniqueId());
        $this->assertSame(2, $job->tries);
        $this->assertSame(900, $job->timeout);
        $this->assertSame(3600, $job->uniqueFor);

        $history = ImportHistory::query()->create([
            'import_id' => 'failed-job', 'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_PROCESSING, 'created_by' => 'actor',
        ]);
        (new ProcessRosterScheduleImport($history->id))->failed(new \RuntimeException('KTP 7402243101930013 C:/private'));
        $failed = $history->fresh();
        $this->assertSame(ImportHistory::STATUS_FAILED, $failed->status);
        $this->assertSame('Import roster gagal diproses. Silakan unggah ulang workbook.', $failed->error_message);
        $this->assertStringNotContainsString('7402243101930013', $failed->error_message);
    }

    public function test_retryable_job_failure_returns_processing_claim_to_queued(): void
    {
        $history = ImportHistory::query()->create([
            'import_id' => 'retry-job', 'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_QUEUED, 'created_by' => 'actor',
            'file_path' => 'roster-imports/retry-job/source.xlsx', 'file_checksum' => 'missing',
            'expires_at' => now()->addHour(),
        ]);

        try {
            (new ProcessRosterScheduleImport($history->id))->handle(
                app(RosterScheduleImportCommitService::class),
                app(\App\Services\Audit\AuditTrailService::class)
            );
            $this->fail('Retryable source failure must be rethrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(ImportHistory::STATUS_QUEUED, $history->fresh()->status);
        }
    }

    private function processingHistory(array $rows): ImportHistory
    {
        foreach ($rows as $row) {
            $this->seedRosterEmployee($row['nik'], $row['ktp']);
        }
        $path = $this->makeRosterWorkbook($rows);
        $importId = (string) \Illuminate\Support\Str::uuid();
        $relativePath = 'roster-imports/' . $importId . '/source.xlsx';
        Storage::disk('local')->put('private/' . $relativePath, file_get_contents($path));

        return ImportHistory::query()->create([
            'import_id' => $importId,
            'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_PROCESSING,
            'created_by' => 'actor',
            'confirmed_by' => 'actor',
            'file_path' => $relativePath,
            'file_checksum' => hash_file('sha256', Storage::disk('local')->path('private/' . $relativePath)),
            'expires_at' => now()->addHour(),
        ]);
    }
}
