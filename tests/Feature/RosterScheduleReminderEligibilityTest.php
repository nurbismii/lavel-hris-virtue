<?php

namespace Tests\Feature;

use App\Console\Kernel;
use App\Jobs\ProcessRosterScheduleImport;
use App\Jobs\SendRosterScheduleReminder;
use App\Models\ImportHistory;
use App\Models\Roster;
use App\Models\RosterSchedule;
use App\Models\User;
use App\Services\Roster\RosterScheduleReminderEligibilityService;
use App\Services\Roster\RosterScheduleImportCommitService;
use App\Services\Audit\AuditTrailService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\CreatesRosterImportSchema;
use Tests\TestCase;

class RosterScheduleReminderEligibilityTest extends TestCase
{
    use CreatesRosterImportSchema;

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
        Carbon::setTestNow(Carbon::parse('2026-09-01 08:00:00'));
        $this->createRosterImportSchema();
        $this->extendReminderSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->cleanRosterImportFixtures();
        Schema::dropAllTables();
        DB::disconnect('sqlite');

        parent::tearDown();
    }

    public function test_eligibility_handles_employee_schedule_date_and_sent_boundaries(): void
    {
        $service = app(RosterScheduleReminderEligibilityService::class);
        $active = $this->schedule('001', 14);
        $h13 = $this->schedule('002', 13);
        $h0 = $this->schedule('003', 0);
        $past = $this->schedule('004', -1);
        $inactiveSchedule = $this->schedule('005', 14, ['is_active' => false]);
        $inactiveEmployee = $this->schedule('006', 14, [], 'RESIGN');
        $sent = $this->schedule('007', 14, ['reminder_sent_at' => now()]);

        $eligibleIds = $service->eligibleQuery(now()->addDays(14), now()->addDays(14))->pluck('id')->all();

        $this->assertSame([$active->id], $eligibleIds);
        $this->assertTrue($service->isEligible($h13));
        $this->assertTrue($service->isEligible($h0));
        $this->assertFalse($service->isEligible($past));
        $this->assertFalse($service->isEligible($inactiveSchedule));
        $this->assertFalse($service->isEligible($inactiveEmployee));
        $this->assertFalse($service->isEligible($sent));
    }

    public function test_linked_pending_or_approved_application_suppresses_but_rejected_allows(): void
    {
        $service = app(RosterScheduleReminderEligibilityService::class);
        $pending = $this->schedule('010', 14);
        $approved = $this->schedule('011', 14);
        $rejected = $this->schedule('012', 14);
        $this->application($pending, 0, 0);
        $this->application($approved, 1, 1);
        $this->application($rejected, 2, 0);

        $this->assertFalse($service->isEligible($pending));
        $this->assertFalse($service->isEligible($approved));
        $this->assertTrue($service->isEligible($rejected));
    }

    public function test_realization_without_application_does_not_suppress_reminder(): void
    {
        $service = app(RosterScheduleReminderEligibilityService::class);
        $schedule = $this->schedule('020', 14, ['realization_type' => RosterSchedule::REALIZATION_CUTI]);

        $this->assertTrue($service->isEligible($schedule));
    }

    public function test_conditional_claim_has_one_winner_and_is_shared_by_late_and_standard_dispatch(): void
    {
        Queue::fake();
        $service = app(RosterScheduleReminderEligibilityService::class);
        $lateSchedule = $this->schedule('030', 4);
        $standardSchedule = $this->schedule('031', 14);

        $firstClaim = $service->claim($lateSchedule->id, now(), now()->addDays(13));
        $secondClaim = (new RosterScheduleReminderEligibilityService())->claim(
            $lateSchedule->id,
            now(),
            now()->addDays(13)
        );
        $lateDispatch = $service->dispatchLate([$lateSchedule->id], now(), now()->addDays(13));

        $this->assertTrue($firstClaim);
        $this->assertFalse($secondClaim);
        $this->assertSame(0, $lateDispatch);
        $this->assertNotNull($lateSchedule->fresh()->reminder_queued_at);

        $this->assertTrue($service->claim($standardSchedule->id, now()->addDays(14), now()->addDays(14)));
        $this->assertSame(0, $service->dispatchScheduled(now()->addDays(14)));
        Queue::assertNotPushed(SendRosterScheduleReminder::class);
    }

    public function test_scheduled_command_selects_exactly_h14(): void
    {
        Queue::fake();
        $h14 = $this->schedule('040', 14);
        $h13 = $this->schedule('041', 13);

        $this->artisan('roster:send-schedule-reminders --limit=10')->assertExitCode(0);

        Queue::assertPushed(SendRosterScheduleReminder::class, function (SendRosterScheduleReminder $job) use ($h14): bool {
            return $job->scheduleId === $h14->id;
        });
        Queue::assertNotPushed(SendRosterScheduleReminder::class, function (SendRosterScheduleReminder $job) use ($h13): bool {
            return $job->scheduleId === $h13->id;
        });
    }

    public function test_scheduler_registers_daily_generation_and_h14_reminders_without_overlap(): void
    {
        $schedule = new Schedule();
        $method = new \ReflectionMethod(Kernel::class, 'schedule');
        $method->setAccessible(true);
        $method->invoke(app(Kernel::class), $schedule);

        foreach ([
            'roster:generate-schedules --years-ahead=2 --limit=5000' => '30 0 * * *',
            'roster:send-schedule-reminders --limit=1000' => '30 7 * * *',
        ] as $command => $expression) {
            $events = collect($schedule->events())
                ->filter(fn ($event): bool => strpos($event->command, $command) !== false)
                ->values();
            $this->assertCount(1, $events);
            $this->assertSame($expression, $events->first()->expression);
            $this->assertTrue($events->first()->withoutOverlapping);
        }
    }

    public function test_late_dispatch_uses_only_supplied_h13_through_h0_ids_with_stagger(): void
    {
        Queue::fake();
        config()->set('roster.reminder_delay_seconds', 3);
        $service = app(RosterScheduleReminderEligibilityService::class);
        $h13 = $this->schedule('050', 13);
        $h0 = $this->schedule('051', 0);
        $past = $this->schedule('052', -1);
        $h14 = $this->schedule('053', 14);
        $notSupplied = $this->schedule('054', 2);

        $count = $service->dispatchLate([$h13->id, $h0->id, $past->id, $h14->id], now(), now()->addDays(13));

        $this->assertSame(2, $count);
        Queue::assertPushed(SendRosterScheduleReminder::class, 2);
        $delays = [];
        Queue::assertPushed(SendRosterScheduleReminder::class, function (SendRosterScheduleReminder $job) use (&$delays): bool {
            $delays[$job->scheduleId] = $job->delay;

            return true;
        });
        $this->assertInstanceOf(\DateTimeInterface::class, $delays[$h13->id]);
        $this->assertInstanceOf(\DateTimeInterface::class, $delays[$h0->id]);
        $this->assertSame(now()->toDateTimeString(), Carbon::instance($delays[$h13->id])->toDateTimeString());
        $this->assertSame(now()->addSeconds(3)->toDateTimeString(), Carbon::instance($delays[$h0->id])->toDateTimeString());
        $this->assertNull($notSupplied->fresh()->reminder_queued_at);
        $this->assertNull($past->fresh()->reminder_queued_at);
        $this->assertNull($h14->fresh()->reminder_queued_at);
    }

    public function test_application_created_after_queueing_skips_job_and_clears_claim(): void
    {
        Notification::fake();
        $service = app(RosterScheduleReminderEligibilityService::class);
        $schedule = $this->schedule('060', 14, ['reminder_queued_at' => now()]);
        $this->userFor($schedule);
        $this->application($schedule, 0, 0);

        (new SendRosterScheduleReminder($schedule->id))->handle($service);

        $fresh = $schedule->fresh();
        $this->assertNull($fresh->reminder_queued_at);
        $this->assertNull($fresh->reminder_sent_at);
        $this->assertNull($fresh->reminder_failed_at);
    }

    public function test_notification_success_marks_sent_and_failure_is_generic_and_retry_safe(): void
    {
        Notification::fake();
        $service = app(RosterScheduleReminderEligibilityService::class);
        $schedule = $this->schedule('070', 14, ['reminder_queued_at' => now()]);
        $user = $this->userFor($schedule);

        (new SendRosterScheduleReminder($schedule->id))->handle($service);

        $this->assertNotNull($schedule->fresh()->reminder_sent_at);
        Notification::assertSentTo($user, \App\Notifications\RosterScheduleReminderNotification::class);

        $failed = $this->schedule('071', 14, ['reminder_queued_at' => now()]);
        (new SendRosterScheduleReminder($failed->id))->failed(new \RuntimeException('KTP 7402243101930001 C:/private/secret.xlsx'));
        $this->assertSame('Pengiriman reminder roster gagal. Sistem akan mencoba kembali bila memungkinkan.', $failed->fresh()->reminder_error);
        $this->assertNull($failed->fresh()->reminder_queued_at);
    }

    public function test_actual_notification_failure_rethrows_for_retry_then_records_safe_final_status(): void
    {
        $service = app(RosterScheduleReminderEligibilityService::class);
        $schedule = $this->schedule('072', 14, ['reminder_queued_at' => now()]);
        $this->userFor($schedule);
        $job = new SendRosterScheduleReminder($schedule->id);
        $exception = new \RuntimeException('KTP 7402243101930072 C:/private/reminder.xlsx');
        Notification::shouldReceive('send')->once()->andThrow($exception);

        try {
            $job->handle($service);
            $this->fail('Kegagalan channel harus diteruskan agar queue dapat mencoba ulang.');
        } catch (\RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        }

        $this->assertNull($schedule->fresh()->reminder_failed_at);
        $job->failed($exception);
        $fresh = $schedule->fresh();
        $this->assertNotNull($fresh->reminder_failed_at);
        $this->assertSame('Pengiriman reminder roster gagal. Sistem akan mencoba kembali bila memungkinkan.', $fresh->reminder_error);
        $this->assertStringNotContainsString('7402243101930072', $fresh->reminder_error);
        $this->assertStringNotContainsString('C:/private', $fresh->reminder_error);
    }

    public function test_successful_import_dispatches_late_candidates_without_persisting_ids(): void
    {
        Queue::fake();
        $nik = '016090980';
        $ktp = '7402243101930080';
        $this->seedRosterEmployee($nik, $ktp);
        $path = $this->makeRosterWorkbook([['nik' => $nik, 'ktp' => $ktp, 'off_start' => '2026-09-10']]);
        $importId = (string) Str::uuid();
        $relativePath = 'roster-imports/' . $importId . '/source.xlsx';
        Storage::disk('local')->put('private/' . $relativePath, file_get_contents($path));
        $history = ImportHistory::create([
            'import_id' => $importId,
            'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_QUEUED,
            'created_by' => 'actor',
            'confirmed_by' => 'actor',
            'file_path' => $relativePath,
            'file_checksum' => hash_file('sha256', Storage::disk('local')->path('private/' . $relativePath)),
            'expires_at' => now()->addHour(),
        ]);
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

        Queue::assertPushed(SendRosterScheduleReminder::class, 1);
        $this->assertSame(ImportHistory::STATUS_COMPLETED, $history->fresh()->status);
        $this->assertStringNotContainsString('late_candidate_schedule_ids', json_encode($history->fresh()->summary));
        $this->assertStringNotContainsString('late_candidate_schedule_ids', json_encode($audit->records));
    }

    public function test_failed_import_never_dispatches_late_candidates(): void
    {
        Queue::fake();
        $history = ImportHistory::create([
            'import_id' => (string) Str::uuid(),
            'import_type' => ImportHistory::TYPE_ROSTER_SCHEDULE,
            'status' => ImportHistory::STATUS_QUEUED,
            'file_path' => 'roster-imports/missing/source.xlsx',
            'file_checksum' => 'missing',
            'expires_at' => now()->addHour(),
        ]);

        try {
            (new ProcessRosterScheduleImport($history->id))->handle(
                app(RosterScheduleImportCommitService::class),
                new class extends AuditTrailService {
                    public function record(array $data): ?\App\Models\AuditTrail
                    {
                        return null;
                    }
                }
            );
            $this->fail('Import gagal tidak boleh masuk ke dispatch reminder late.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Sumber import roster tidak tersedia.', $exception->getMessage());
        }

        Queue::assertNotPushed(SendRosterScheduleReminder::class);
    }

    private function extendReminderSchema(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('status')->nullable();
        });
        Schema::table('roster_schedules', function (Blueprint $table): void {
            $table->timestamp('reminder_queued_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('reminder_failed_at')->nullable();
            $table->string('reminder_email')->nullable();
            $table->string('reminder_error')->nullable();
        });
        Schema::table('cuti_roster', function (Blueprint $table): void {
            $table->unsignedTinyInteger('status_pengajuan')->nullable();
            $table->unsignedTinyInteger('status_pengajuan_hrd')->nullable();
            $table->timestamps();
        });
    }

    private function schedule(string $suffix, int $days, array $overrides = [], string $employeeStatus = 'AKTIF'): RosterSchedule
    {
        $nik = 'NIK' . $suffix;
        $this->seedRosterEmployee($nik, str_pad($suffix, 16, '0', STR_PAD_LEFT), 'Karyawan ' . $suffix, $employeeStatus);

        return RosterSchedule::create(array_merge([
            'employee_nik' => $nik,
            'period_year' => 2026,
            'period_number' => 1,
            'work_start' => now()->subDays(56)->toDateString(),
            'work_end' => now()->subDay()->toDateString(),
            'off_start' => now()->addDays($days)->toDateString(),
            'off_end' => now()->addDays($days + 13)->toDateString(),
            'earned_off_days' => 5,
            'realization_type' => RosterSchedule::REALIZATION_PENDING,
            'is_active' => true,
        ], $overrides));
    }

    private function application(RosterSchedule $schedule, int $hod, int $hrd): Roster
    {
        return Roster::create([
            'roster_schedule_id' => $schedule->id,
            'status_pengajuan' => $hod,
            'status_pengajuan_hrd' => $hrd,
        ]);
    }

    private function userFor(RosterSchedule $schedule): User
    {
        return User::create([
            'id' => 'user-' . $schedule->id,
            'name' => 'Karyawan ' . $schedule->id,
            'email' => 'employee-' . $schedule->id . '@example.test',
            'nik_karyawan' => $schedule->employee_nik,
            'status' => 'aktif',
        ]);
    }
}
