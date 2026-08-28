<?php

namespace Tests\Feature;

use App\Jobs\ProcessRosterScheduleImport;
use App\Models\ImportHistory;
use App\Models\RosterSchedule;
use App\Models\RosterScheduleHistory;
use App\Services\Audit\AuditTrailService;
use App\Services\Roster\RosterScheduleImportCommitService;
use App\Services\Roster\RosterScheduleWorkbookImportService;
use Carbon\Carbon;
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
        Carbon::setTestNow();
        $this->cleanRosterImportFixtures();
        Schema::dropAllTables();
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_checksum_changed_and_missing_source_block_before_writes(): void
    {
        $history = $this->processingHistory([['nik' => '016090950', 'ktp' => '7402243101930010']]);
        $history->update(['file_checksum' => 'changed']);

        try {
            app(RosterScheduleImportCommitService::class)->commit($history->fresh());
            $this->fail('Checksum yang berubah harus menghentikan import.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Sumber import roster berubah.', $exception->getMessage());
        }

        $this->assertSame(0, RosterSchedule::query()->count());
        $this->assertSame(0, RosterScheduleHistory::query()->count());
        $this->assertSame(ImportHistory::STATUS_PROCESSING, $history->fresh()->status);
    }

    public function test_identity_changed_after_preview_blocks_before_writes(): void
    {
        $history = $this->processingHistory([['nik' => '016090960', 'ktp' => '7402243101930020']]);
        DB::table('employees')->where('nik', '016090960')->update(['no_ktp' => '7402243101939999']);

        try {
            app(RosterScheduleImportCommitService::class)->commit($history);
            $this->fail('Perubahan identitas harus menghentikan import.');
        } catch (\RuntimeException $exception) {
            $this->assertStringNotContainsString('7402243101930020', $exception->getMessage());
        }

        $this->assertSame(0, RosterSchedule::query()->count());
        $this->assertSame(0, RosterScheduleHistory::query()->count());
        $this->assertSame(ImportHistory::STATUS_PROCESSING, $history->fresh()->status);
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

    public function test_many_period_entries_use_bounded_schedule_and_history_queries(): void
    {
        $rows = [];
        for ($index = 0; $index < 100; $index++) {
            $rows[] = [
                'nik' => '016091' . str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'ktp' => '740224310193' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            ];
        }
        $history = $this->processingHistory($rows);
        DB::table('employees')->update(['status_resign' => 'NONAKTIF']);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, 'roster_schedules') || str_contains($query->sql, 'roster_schedule_histories')) {
                $queries[] = $query->sql;
            }
        });

        app(RosterScheduleImportCommitService::class)->commit($history);

        $this->assertLessThan(20, count($queries));
        $this->assertSame(100, RosterSchedule::query()->where('source', RosterSchedule::SOURCE_IMPORT)->count());
    }

    public function test_active_employee_generates_to_second_year_end_while_inactive_stops_at_import(): void
    {
        Carbon::setTestNow('2026-08-28 09:00:00');
        $history = $this->processingHistory([
            ['nik' => '016090961', 'ktp' => '7402243101930021'],
            ['nik' => '016090962', 'ktp' => '7402243101930022'],
        ]);
        DB::table('employees')->where('nik', '016090962')->update(['status_resign' => 'NONAKTIF']);

        $result = app(RosterScheduleImportCommitService::class)->commit($history);

        $horizon = Carbon::today()->addYears(2)->endOfYear();
        $activeDates = RosterSchedule::query()->where('employee_nik', '016090961')->orderBy('off_start')->pluck('off_start');
        $inactiveDates = RosterSchedule::query()->where('employee_nik', '016090962')->pluck('off_start');
        $lastActive = Carbon::parse((string) $activeDates->last());
        $this->assertGreaterThan(1, $activeDates->count());
        $this->assertTrue($lastActive->lte($horizon));
        $this->assertTrue($lastActive->gte($horizon->copy()->subDays(83)));
        $this->assertCount(1, $inactiveDates);
        $this->assertGreaterThan(0, $result['future_generated']);
        Carbon::setTestNow();
    }

    public function test_confirmed_history_and_noncolliding_manual_schedule_are_preserved(): void
    {
        $history = $this->processingHistory([[
            'nik' => '016090963',
            'ktp' => '7402243101930023',
            'remark' => 'I. INSENTIF',
        ]]);
        $imported = RosterSchedule::query()->create([
            'employee_nik' => '016090963', 'period_year' => 2026, 'period_number' => 1,
            'work_start' => '2026-07-02', 'work_end' => '2026-09-09', 'off_start' => '2026-09-10',
            'off_end' => '2026-09-23', 'realization_type' => RosterSchedule::REALIZATION_CUTI,
            'source' => RosterSchedule::SOURCE_IMPORT, 'is_active' => true,
        ]);
        $review = RosterScheduleHistory::query()->create([
            'roster_schedule_id' => $imported->id, 'employee_nik' => '016090963', 'period_year' => 2026,
            'period_number' => 1, 'scheduled_off_start' => '2026-09-10', 'scheduled_off_end' => '2026-09-23',
            'classification' => RosterScheduleHistory::CLASSIFICATION_CUTI,
            'review_status' => RosterScheduleHistory::REVIEW_CONFIRMED, 'review_note' => 'Keputusan HR final',
            'source_file' => 'source.xlsx', 'imported_at' => now(),
        ]);
        $manual = RosterSchedule::query()->create([
            'employee_nik' => '016090963', 'period_year' => 2026, 'period_number' => 9,
            'work_start' => '2026-09-24', 'work_end' => '2026-11-15', 'off_start' => '2026-11-16',
            'off_end' => '2026-11-29', 'realization_type' => RosterSchedule::REALIZATION_PENDING,
            'source' => RosterSchedule::SOURCE_MANUAL, 'notes' => 'Jadwal manual HR', 'is_active' => true,
        ]);
        $manualSnapshot = $manual->fresh()->getAttributes();

        app(RosterScheduleImportCommitService::class)->commit($history);

        $this->assertSame(RosterSchedule::REALIZATION_CUTI, $imported->fresh()->realization_type);
        $this->assertSame(RosterScheduleHistory::CLASSIFICATION_CUTI, $review->fresh()->classification);
        $this->assertSame(RosterScheduleHistory::REVIEW_CONFIRMED, $review->fresh()->review_status);
        $this->assertSame('Keputusan HR final', $review->fresh()->review_note);
        $this->assertSame($manualSnapshot, $manual->fresh()->getAttributes());
    }

    public function test_second_employee_database_failure_rolls_back_all_import_writes(): void
    {
        $history = $this->processingHistory([
            ['nik' => '016090964', 'ktp' => '7402243101930024'],
            ['nik' => '016090965', 'ktp' => '7402243101930025'],
        ]);
        DB::unprepared("CREATE TRIGGER fail_second_roster BEFORE INSERT ON roster_schedules
            WHEN NEW.employee_nik = '016090965'
            BEGIN SELECT RAISE(ABORT, 'forced roster failure'); END");

        try {
            app(RosterScheduleImportCommitService::class)->commit($history);
            $this->fail('Kegagalan employee kedua harus membatalkan seluruh transaksi.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('forced roster failure', $exception->getMessage());
        }

        $this->assertSame(0, RosterSchedule::query()->count());
        $this->assertSame(0, RosterScheduleHistory::query()->count());
        $this->assertSame(ImportHistory::STATUS_PROCESSING, $history->fresh()->status);
    }

    public function test_completed_job_audit_contains_only_safe_counts(): void
    {
        $ktp = '7402243101930026';
        $history = $this->processingHistory([['nik' => '016090966', 'ktp' => $ktp]]);
        $history->update(['status' => ImportHistory::STATUS_QUEUED]);
        $audit = new class extends AuditTrailService {
            public array $records = [];

            public function record(array $data): ?\App\Models\AuditTrail
            {
                $this->records[] = $data;

                return null;
            }
        };

        (new ProcessRosterScheduleImport($history->id))->handle(
            app(RosterScheduleImportCommitService::class),
            $audit
        );

        $this->assertSame(ImportHistory::STATUS_COMPLETED, $history->fresh()->status);
        $this->assertCount(1, $audit->records);
        $encoded = json_encode($audit->records[0], JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString($ktp, $encoded);
        $this->assertStringNotContainsString('late_candidate_schedule_ids', $encoded);
        $this->assertStringNotContainsString((string) $history->file_path, $encoded);
        $this->assertSame('roster_schedule_import.completed', $audit->records[0]['event']);
        $this->assertSame([
            'employees', 'history_created', 'history_updated', 'unchanged', 'future_generated', 'need_review',
        ], array_keys($audit->records[0]['metadata']['summary']));
    }

    public function test_cli_adapter_keeps_signature_and_dry_run_has_zero_writes(): void
    {
        $method = new \ReflectionMethod(RosterScheduleWorkbookImportService::class, 'import');
        $this->assertSame(['path', 'dryRun', 'actorId'], array_map(
            fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            $method->getParameters()
        ));
        $this->assertSame('string', (string) $method->getParameters()[0]->getType());
        $this->assertSame('bool', (string) $method->getParameters()[1]->getType());
        $this->assertSame('?string', (string) $method->getParameters()[2]->getType());

        $nik = '016090967';
        $ktp = '7402243101930027';
        $this->seedRosterEmployee($nik, $ktp);
        $path = $this->makeRosterWorkbook([['nik' => $nik, 'ktp' => $ktp]]);
        $service = app(RosterScheduleWorkbookImportService::class);

        $dryRun = $service->import($path, true, 'cli-actor');
        $this->assertSame(1, $dryRun['total_rows']);
        $this->assertSame(0, $dryRun['blocker_count']);
        $this->assertSame(0, ImportHistory::query()->count());
        $this->assertSame(0, RosterSchedule::query()->count());
        $this->assertSame(0, RosterScheduleHistory::query()->count());

        $result = $service->import($path, false, 'cli-actor');
        $this->assertSame(1, $result['employees']);
        $this->assertSame(ImportHistory::STATUS_COMPLETED, ImportHistory::query()->firstOrFail()->status);
        $this->assertGreaterThan(0, RosterSchedule::query()->count());
        $this->assertGreaterThan(0, RosterScheduleHistory::query()->count());
        $this->assertStringNotContainsString($ktp, json_encode($result));
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
