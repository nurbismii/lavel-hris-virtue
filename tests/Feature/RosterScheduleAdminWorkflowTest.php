<?php

namespace Tests\Feature;

use App\Jobs\SendRosterScheduleReminder;
use App\Models\Roster;
use App\Models\RosterSchedule;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use Carbon\Carbon;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesRosterImportSchema;
use Tests\TestCase;

class RosterScheduleAdminWorkflowTest extends TestCase
{
    use CreatesRosterImportSchema;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->cleanRosterImportFixtures();
        Schema::dropAllTables();
        DB::disconnect('sqlite');

        parent::tearDown();
    }

    public function test_index_prioritizes_overdue_schedules_and_renders_warning_only_for_active_pending_rows(): void
    {
        $hr = $this->hrUser();

        $this->schedule('NIK-OVERDUE-NEAR', '2026-08-31');
        $this->schedule('NIK-OVERDUE-OLD', '2026-08-20');
        $this->schedule('NIK-TODAY', '2026-09-01');
        $this->schedule('NIK-FUTURE', '2026-09-10');
        $this->schedule('NIK-COMPLETED', '2026-08-28', RosterSchedule::REALIZATION_CUTI);
        $this->schedule('NIK-INACTIVE', '2026-08-30', RosterSchedule::REALIZATION_PENDING, false);

        $response = $this->actingAs($hr)->get(route('roster-schedules.index'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'NIK-OVERDUE-NEAR',
            'NIK-OVERDUE-OLD',
            'NIK-TODAY',
            'NIK-FUTURE',
            'NIK-COMPLETED',
        ]);
        $response->assertSee('Terlambat Mengajukan');
        $response->assertSee('Terlambat 1 hari');

        preg_match_all('/<tr>.*?<\/tr>/s', $response->getContent(), $rows);
        $inactiveRow = collect($rows[0])->first(function (string $row): bool {
            return strpos($row, 'NIK-INACTIVE') !== false;
        });

        $this->assertNotNull($inactiveRow);
        $this->assertStringNotContainsString('Terlambat Mengajukan', $inactiveRow);
    }

    public function test_hr_can_queue_overdue_reminder_once_and_action_is_audited(): void
    {
        Queue::fake();
        $hr = $this->hrUser();
        $schedule = $this->schedule('NIK-OVERDUE-REMINDER', '2026-08-31');
        $audit = $this->fakeAudit();

        $this->actingAs($hr)
            ->post(route('roster-schedules.reminder.overdue', $schedule))
            ->assertRedirect();
        $this->actingAs($hr)
            ->post(route('roster-schedules.reminder.overdue', $schedule))
            ->assertRedirect();

        Queue::assertPushed(SendRosterScheduleReminder::class, 1);
        Queue::assertPushed(SendRosterScheduleReminder::class, function (SendRosterScheduleReminder $job) use ($schedule): bool {
            return $job->scheduleId === $schedule->id
                && $job->mode === SendRosterScheduleReminder::MODE_OVERDUE;
        });
        $this->assertNotNull($schedule->fresh()->reminder_queued_at);
        $this->assertCount(2, $audit->records);
        foreach ($audit->records as $record) {
            $this->assertSame('roster_schedule.overdue_reminder_requested', $record['event']);
            $this->assertSame('requested', $record['metadata']['status']);
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                $record['metadata']['context_id']
            );
        }
    }

    public function test_overdue_reminder_audit_failure_prevents_dispatch_and_queued_success_feedback(): void
    {
        Queue::fake();
        $hr = $this->hrUser();
        $schedule = $this->schedule('NIK-OVERDUE-AUDIT-FAIL', '2026-08-31');
        $this->app->instance(AuditTrailService::class, new class extends AuditTrailService {
            public function record(array $data): ?\App\Models\AuditTrail
            {
                return null;
            }
        });

        $response = $this->actingAs($hr)
            ->post(route('roster-schedules.reminder.overdue', $schedule))
            ->assertRedirect();

        $response->assertSessionHas('alert.config', function (string $config): bool {
            $alert = json_decode($config, true);

            return ($alert['icon'] ?? null) === 'error'
                && ($alert['title'] ?? null) === 'Gagal';
        });
        Queue::assertNothingPushed();
        $this->assertNull($schedule->fresh()->reminder_queued_at);
    }

    public function test_overdue_dispatch_failure_keeps_a_truthful_durable_request_audit(): void
    {
        $hr = $this->hrUser();
        $schedule = $this->schedule('NIK-OVERDUE-DISPATCH-FAIL', '2026-08-31');
        $audit = $this->fakeAudit();
        $dispatcher = \Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->andThrow(new \RuntimeException('private/NIK-OVERDUE-DISPATCH-FAIL.xlsx'));
        $this->app->instance(Dispatcher::class, $dispatcher);

        $response = $this->actingAs($hr)
            ->post(route('roster-schedules.reminder.overdue', $schedule))
            ->assertRedirect();

        $response->assertSessionHas('alert.config', function (string $config): bool {
            $alert = json_decode($config, true);

            return ($alert['icon'] ?? null) === 'warning'
                && ($alert['title'] ?? null) === 'Belum Diproses'
                && strpos($config, 'NIK-OVERDUE-DISPATCH-FAIL') === false;
        });
        $this->assertNull($schedule->fresh()->reminder_queued_at);
        $this->assertCount(1, $audit->records);
        $this->assertSame('roster_schedule.overdue_reminder_requested', $audit->records[0]['event']);
        $this->assertSame('requested', $audit->records[0]['metadata']['status']);
    }

    public function test_stale_unique_lock_audits_the_request_but_does_not_show_queued_success(): void
    {
        Queue::fake();
        $hr = $this->hrUser();
        $schedule = $this->schedule('NIK-OVERDUE-LOCKED', '2026-08-31');
        $audit = $this->fakeAudit();
        $job = new SendRosterScheduleReminder($schedule->id, SendRosterScheduleReminder::MODE_OVERDUE);
        $lock = app(CacheRepository::class)->lock($this->uniqueLockKey($job), $job->uniqueFor);
        $this->assertTrue($lock->get());

        try {
            $response = $this->actingAs($hr)
                ->post(route('roster-schedules.reminder.overdue', $schedule))
                ->assertRedirect();

            $response->assertSessionHas('alert.config', function (string $config): bool {
                $alert = json_decode($config, true);

                return ($alert['icon'] ?? null) === 'warning'
                    && ($alert['title'] ?? null) === 'Belum Diproses';
            });
            Queue::assertNothingPushed();
            $this->assertNull($schedule->fresh()->reminder_queued_at);
            $this->assertCount(1, $audit->records);
            $this->assertSame('roster_schedule.overdue_reminder_requested', $audit->records[0]['event']);
        } finally {
            $lock->release();
        }
    }

    public function test_overdue_reminder_rejects_employee_and_hr_without_menu_access(): void
    {
        Queue::fake();
        $schedule = $this->schedule('NIK-OVERDUE-AUTH', '2026-08-31');
        $employee = $this->userWithRole('employee-reminder', 'Employee', ['roster_schedule']);
        $hrWithoutMenu = $this->userWithRole('hr-no-menu-reminder', 'HR', []);

        $this->actingAs($employee)
            ->post(route('roster-schedules.reminder.overdue', $schedule))
            ->assertForbidden();
        $this->actingAs($hrWithoutMenu)
            ->post(route('roster-schedules.reminder.overdue', $schedule))
            ->assertForbidden();

        Queue::assertNothingPushed();
        $this->assertNull($schedule->fresh()->reminder_queued_at);
    }

    public function test_future_completed_and_cooldown_schedules_do_not_queue_overdue_reminders(): void
    {
        Queue::fake();
        config()->set('roster.overdue_reminder_cooldown_hours', 24);
        $hr = $this->hrUser();
        $future = $this->schedule('NIK-FUTURE-REMINDER', '2026-09-02');
        $completed = $this->schedule('NIK-COMPLETED-REMINDER', '2026-08-30', RosterSchedule::REALIZATION_CUTI);
        $cooldown = $this->schedule('NIK-COOLDOWN-REMINDER', '2026-08-29');
        $cooldown->update(['reminder_sent_at' => now()->subHours(23)]);

        foreach ([$future, $completed, $cooldown] as $schedule) {
            $this->actingAs($hr)
                ->post(route('roster-schedules.reminder.overdue', $schedule))
                ->assertRedirect();
        }

        Queue::assertNothingPushed();
        $this->assertNull($future->fresh()->reminder_queued_at);
        $this->assertNull($completed->fresh()->reminder_queued_at);
        $this->assertNull($cooldown->fresh()->reminder_queued_at);
    }

    public function test_index_renders_truthful_overdue_reminder_states(): void
    {
        $hr = $this->hrUser();
        $eligible = $this->schedule('NIK-REMINDER-ELIGIBLE', '2026-08-31');
        $queued = $this->schedule('NIK-REMINDER-QUEUED', '2026-08-30');
        $queued->update(['reminder_queued_at' => now()]);
        $cooldown = $this->schedule('NIK-REMINDER-COOLDOWN', '2026-08-29');
        $cooldown->update(['reminder_sent_at' => now()->subHours(23)]);

        $response = $this->actingAs($hr)->get(route('roster-schedules.index'));

        $response->assertOk();
        $response->assertSee(route('roster-schedules.reminder.overdue', $eligible));
        $response->assertSee('Kirim Reminder Lagi');
        $response->assertSee('Dalam antrean');
        $response->assertSee('Dapat dikirim lagi');
        $response->assertSee('Memasukkan ke antrean...');
    }

    public function test_index_disables_overdue_reminder_for_resigned_employee_and_active_application(): void
    {
        $viewSource = file_get_contents(resource_path('views/admin/roster-schedules/index.blade.php'));
        $this->assertStringNotContainsString('@disabled(', $viewSource);

        $hr = $this->hrUser();
        $resigned = $this->schedule('NIK-REMINDER-RESIGNED', '2026-08-31');
        DB::table('employees')->where('nik', $resigned->employee_nik)->update(['status_resign' => 'RESIGN']);
        $resigned->update(['reminder_sent_at' => now()->subHours(23)]);
        $applied = $this->schedule('NIK-REMINDER-APPLIED', '2026-08-30');
        $applied->update(['reminder_queued_at' => now()]);
        DB::table('cuti_roster')->insert([
            'roster_schedule_id' => $applied->id,
            'status_pengajuan' => 0,
            'status_pengajuan_hrd' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach (range(1, 3) as $index) {
            $additionalApplied = $this->schedule('NIK-REMINDER-APPLIED-' . $index, '2026-08-2' . $index);
            DB::table('cuti_roster')->insert([
                'roster_schedule_id' => $additionalApplied->id,
                'status_pengajuan' => 0,
                'status_pengajuan_hrd' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $applicationQueries = [];
        DB::listen(function ($query) use (&$applicationQueries): void {
            $sql = strtolower($query->sql);
            if (strpos($sql, 'cuti_roster') !== false && strpos($sql, 'roster_schedule_id') !== false) {
                $applicationQueries[] = $query->sql;
            }
        });

        $response = $this->actingAs($hr)->get(route('roster-schedules.index'));

        $response->assertOk();
        $this->assertCount(2, $applicationQueries);
        preg_match_all('/<tr>.*?<\/tr>/s', $response->getContent(), $rows);

        foreach ([
            [$resigned, 'Karyawan tidak aktif', 'Dalam cooldown'],
            [$applied, 'Pengajuan digital aktif', 'Dalam antrean'],
        ] as [$schedule, $reason, $misleadingState]) {
            $row = collect($rows[0])->first(function (string $row) use ($schedule): bool {
                return strpos($row, $schedule->employee_nik) !== false;
            });

            $this->assertNotNull($row);
            $this->assertStringContainsString('disabled', $row);
            $this->assertStringContainsString($reason, $row);
            $this->assertStringNotContainsString('Kirim Reminder Lagi', $row);
            $this->assertSame(1, preg_match('/<button[^>]*disabled[^>]*>(.*?)<\/button>/s', $row, $button));
            $this->assertStringContainsString($reason, $button[1]);
            $this->assertStringNotContainsString($misleadingState, $button[1]);
        }
    }

    public function test_hr_can_record_both_manual_submission_types_without_creating_digital_application(): void
    {
        $hr = $this->hrUser();
        $audit = $this->fakeAudit();

        foreach ([
            RosterSchedule::REALIZATION_CUTI => 'RST/HR/IX/2026',
            RosterSchedule::REALIZATION_INSENTIF => 'INS/HR/IX/2026',
        ] as $type => $reference) {
            $schedule = $this->schedule('NIK-MANUAL-' . strtoupper($type), '2026-09-10');

            $this->actingAs($hr)->post(
                route('roster-schedules.manual-submission.store', $schedule),
                [
                    'realization_type' => $type,
                    'manual_reference_number' => $reference,
                    'manual_submission_note' => 'Berkas fisik diterima HR.',
                    'manual_schedule_id' => (string) $schedule->id,
                ]
            )->assertRedirect();

            $fresh = $schedule->fresh();
            $this->assertSame($type, $fresh->realization_type);
            $this->assertSame($hr->id, $fresh->manual_submitted_by);
            $this->assertSame(now()->toDateTimeString(), $fresh->manual_submitted_at->toDateTimeString());
            $this->assertSame($reference, $fresh->manual_reference_number);
            $this->assertSame('Berkas fisik diterima HR.', $fresh->manual_submission_note);
            $this->assertSame($hr->id, $fresh->updated_by);
            $this->assertNull($fresh->reminder_queued_at);
            $this->assertSame(0, Roster::where('roster_schedule_id', $schedule->id)->count());
        }

        $this->assertCount(2, $audit->records);
        $this->assertSame('roster_schedule.manual_submission_recorded', $audit->records[0]['event']);
        $this->assertSame('offline', $audit->records[0]['metadata']['submission_channel']);
        $this->assertSame($hr, $audit->records[0]['actor']);
        $this->assertStringNotContainsString('Berkas fisik diterima HR.', json_encode($audit->records));
    }

    public function test_manual_submission_validation_rejects_invalid_types_and_oversized_metadata(): void
    {
        $hr = $this->hrUser();
        $schedule = $this->schedule('NIK-MANUAL-VALIDATION', '2026-09-10');

        foreach ([
            ['realization_type' => RosterSchedule::REALIZATION_PENDING],
            ['realization_type' => 'unknown'],
            [
                'realization_type' => RosterSchedule::REALIZATION_CUTI,
                'manual_reference_number' => str_repeat('R', 101),
            ],
            [
                'realization_type' => RosterSchedule::REALIZATION_INSENTIF,
                'manual_submission_note' => str_repeat('N', 501),
            ],
        ] as $payload) {
            $this->actingAs($hr)
                ->postJson(route('roster-schedules.manual-submission.store', $schedule), $payload)
                ->assertStatus(422)
                ->assertJsonValidationErrors(array_key_exists('manual_reference_number', $payload)
                    ? 'manual_reference_number'
                    : (array_key_exists('manual_submission_note', $payload) ? 'manual_submission_note' : 'realization_type'));
        }

        $this->actingAs($hr)
            ->from(route('roster-schedules.index'))
            ->post(route('roster-schedules.manual-submission.store', $schedule), [
                'realization_type' => RosterSchedule::REALIZATION_PENDING,
                'manual_schedule_id' => (string) $schedule->id,
            ])
            ->assertRedirect(route('roster-schedules.index'))
            ->assertSessionHasErrors('realization_type')
            ->assertSessionHasInput('manual_schedule_id', (string) $schedule->id);

        $this->assertSame(RosterSchedule::REALIZATION_PENDING, $schedule->fresh()->realization_type);
    }

    public function test_manual_submission_rejects_unauthorized_roles_and_missing_menu_access(): void
    {
        $schedule = $this->schedule('NIK-MANUAL-AUTH', '2026-09-10');
        $employee = $this->userWithRole('employee-manual', 'Employee', ['roster_schedule']);
        $hrWithoutMenu = $this->userWithRole('hr-no-menu-manual', 'HR', []);

        foreach ([$employee, $hrWithoutMenu] as $actor) {
            $this->actingAs($actor)->post(
                route('roster-schedules.manual-submission.store', $schedule),
                ['realization_type' => RosterSchedule::REALIZATION_CUTI]
            )->assertForbidden();
        }

        $this->assertSame(RosterSchedule::REALIZATION_PENDING, $schedule->fresh()->realization_type);
    }

    public function test_manual_submission_revalidates_locked_schedule_and_active_digital_applications(): void
    {
        $hr = $this->hrUser();
        $inactive = $this->schedule('NIK-MANUAL-INACTIVE', '2026-09-10', RosterSchedule::REALIZATION_PENDING, false);
        $realized = $this->schedule('NIK-MANUAL-REALIZED', '2026-09-11', RosterSchedule::REALIZATION_CUTI);
        $pendingApplication = $this->schedule('NIK-MANUAL-DIGITAL-PENDING', '2026-09-12');
        $approvedApplication = $this->schedule('NIK-MANUAL-DIGITAL-APPROVED', '2026-09-13');
        $this->application($pendingApplication, 0, 0);
        $this->application($approvedApplication, 1, 1);

        foreach ([$inactive, $realized, $pendingApplication, $approvedApplication] as $schedule) {
            $this->actingAs($hr)
                ->post(route('roster-schedules.manual-submission.store', $schedule), [
                    'realization_type' => RosterSchedule::REALIZATION_INSENTIF,
                ])
                ->assertRedirect()
                ->assertSessionHasErrors('realization_type');
        }

        $this->assertSame(RosterSchedule::REALIZATION_PENDING, $inactive->fresh()->realization_type);
        $this->assertSame(RosterSchedule::REALIZATION_CUTI, $realized->fresh()->realization_type);
        $this->assertSame(RosterSchedule::REALIZATION_PENDING, $pendingApplication->fresh()->realization_type);
        $this->assertSame(RosterSchedule::REALIZATION_PENDING, $approvedApplication->fresh()->realization_type);
        $this->assertDatabaseCount('cuti_roster', 2);
    }

    public function test_manual_submission_is_idempotent_and_does_not_overwrite_original_record(): void
    {
        $hr = $this->hrUser();
        $this->fakeAudit();
        $otherHr = $this->userWithRole('other-hr-manual', 'HR', ['roster_schedule']);
        $schedule = $this->schedule('NIK-MANUAL-IDEMPOTENT', '2026-09-10');

        $this->actingAs($hr)->post(route('roster-schedules.manual-submission.store', $schedule), [
            'realization_type' => RosterSchedule::REALIZATION_CUTI,
            'manual_reference_number' => 'FIRST/2026',
            'manual_submission_note' => 'Catatan pertama.',
        ])->assertRedirect();
        $original = $schedule->fresh();

        Carbon::setTestNow(now()->addHour());
        $this->actingAs($otherHr)->post(route('roster-schedules.manual-submission.store', $schedule), [
            'realization_type' => RosterSchedule::REALIZATION_INSENTIF,
            'manual_reference_number' => 'SECOND/2026',
            'manual_submission_note' => 'Catatan kedua.',
        ])->assertRedirect()->assertSessionHasErrors('realization_type');

        $fresh = $schedule->fresh();
        $this->assertSame($original->realization_type, $fresh->realization_type);
        $this->assertSame($original->manual_submitted_by, $fresh->manual_submitted_by);
        $this->assertSame($original->manual_submitted_at->toDateTimeString(), $fresh->manual_submitted_at->toDateTimeString());
        $this->assertSame($original->manual_reference_number, $fresh->manual_reference_number);
        $this->assertSame($original->manual_submission_note, $fresh->manual_submission_note);
        $this->assertSame(0, Roster::where('roster_schedule_id', $schedule->id)->count());
    }

    public function test_manual_submission_rolls_back_and_returns_generic_feedback_on_unexpected_failure(): void
    {
        $hr = $this->hrUser();
        $schedule = $this->schedule('NIK-MANUAL-ROLLBACK', '2026-09-10');
        $originalReminderQueuedAt = now()->subMinute()->startOfSecond();
        $schedule->forceFill([
            'reminder_queued_at' => $originalReminderQueuedAt,
            'updated_by' => 'original-actor',
        ])->save();
        $this->app->instance(AuditTrailService::class, new class extends AuditTrailService {
            public function record(array $data): ?\App\Models\AuditTrail
            {
                return null;
            }
        });

        $response = $this->actingAs($hr)
            ->from(route('roster-schedules.index'))
            ->post(route('roster-schedules.manual-submission.store', $schedule), [
                'realization_type' => RosterSchedule::REALIZATION_CUTI,
                'manual_reference_number' => 'ROLLBACK/2026',
                'manual_submission_note' => 'Restore this input.',
                'manual_schedule_id' => (string) $schedule->id,
            ]);

        $response->assertRedirect(route('roster-schedules.index'))
            ->assertSessionHasInput('manual_reference_number', 'ROLLBACK/2026')
            ->assertSessionHasInput('manual_schedule_id', (string) $schedule->id)
            ->assertSessionHas('alert.config', function (string $config): bool {
                $alert = json_decode($config, true);

                return ($alert['icon'] ?? null) === 'error'
                    && ($alert['title'] ?? null) === 'Gagal'
                    && ($alert['text'] ?? null) === 'Pengajuan manual gagal dicatat. Silakan coba lagi.';
            });

        $fresh = $schedule->fresh();
        $this->assertSame(RosterSchedule::REALIZATION_PENDING, $fresh->realization_type);
        $this->assertNull($fresh->manual_submitted_at);
        $this->assertNull($fresh->manual_submitted_by);
        $this->assertNull($fresh->manual_reference_number);
        $this->assertNull($fresh->manual_submission_note);
        $this->assertSame($originalReminderQueuedAt->toDateTimeString(), $fresh->reminder_queued_at->toDateTimeString());
        $this->assertSame('original-actor', $fresh->updated_by);
    }

    public function test_index_renders_manual_action_and_escaped_manual_status_details(): void
    {
        $hr = $this->hrUser();
        $pending = $this->schedule('NIK-MANUAL-UI-PENDING', '2026-09-10');
        $manual = $this->schedule('NIK-MANUAL-UI-DONE', '2026-09-11', RosterSchedule::REALIZATION_CUTI);
        $manual->forceFill([
            'manual_submitted_at' => now(),
            'manual_submitted_by' => $hr->id,
            'manual_reference_number' => 'REF/<script>alert(1)</script>',
            'manual_submission_note' => '<script>private note</script>',
        ])->save();

        $response = $this->actingAs($hr)->get(route('roster-schedules.index'));

        $response->assertOk();
        $response->assertSee(route('roster-schedules.manual-submission.store', $pending), false);
        $response->assertSee('Catat Pengajuan Manual');
        $response->assertSee('Pengajuan Manual');
        $response->assertSee('REF/&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $response->assertDontSee('<script>private note</script>', false);
        $response->assertSee($hr->name);
        $response->assertSee('tidak membuat approval digital');
        $response->assertSee('Menyimpan...');

        preg_match_all('/<tr>.*?<\/tr>/s', $response->getContent(), $rows);
        $manualRow = collect($rows[0])->first(function (string $row) use ($manual): bool {
            return strpos($row, $manual->employee_nik) !== false;
        });
        $this->assertNotNull($manualRow);
        $this->assertStringNotContainsString('Catat Pengajuan Manual', $manualRow);
        $this->assertStringNotContainsString(route('roster-schedules.manual-submission.store', $manual), $manualRow);
    }

    private function application(RosterSchedule $schedule, int $hod, int $hrd): Roster
    {
        return Roster::create([
            'roster_schedule_id' => $schedule->id,
            'status_pengajuan' => $hod,
            'status_pengajuan_hrd' => $hrd,
        ]);
    }

    private function hrUser(): User
    {
        $this->seedRosterEmployee('HR-ROSTER-PRIORITY', '9999999999999999', 'HR Roster');

        $roleId = DB::table('roles')->insertGetId([
            'permission_role' => 'HR',
            'menu_permissions' => json_encode(['roster_schedule']),
        ]);

        return User::create([
            'id' => 'hr-roster-priority',
            'name' => 'HR Roster',
            'role_id' => $roleId,
            'nik_karyawan' => 'HR-ROSTER-PRIORITY',
        ]);
    }

    private function userWithRole(string $id, string $role, array $menuPermissions): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'permission_role' => $role,
            'menu_permissions' => json_encode($menuPermissions),
        ]);

        return User::create([
            'id' => $id,
            'name' => $id,
            'role_id' => $roleId,
        ]);
    }

    private function uniqueLockKey(SendRosterScheduleReminder $job): string
    {
        if (method_exists(UniqueLock::class, 'getKey')) {
            return UniqueLock::getKey($job);
        }

        $uniqueId = method_exists($job, 'uniqueId')
            ? $job->uniqueId()
            : ($job->uniqueId ?? '');

        return 'laravel_unique_job:' . get_class($job) . $uniqueId;
    }

    private function fakeAudit(): AuditTrailService
    {
        $audit = new class extends AuditTrailService {
            public array $records = [];

            public function record(array $data): ?\App\Models\AuditTrail
            {
                $this->records[] = $data;

                return new \App\Models\AuditTrail();
            }
        };
        $this->app->instance(AuditTrailService::class, $audit);

        return $audit;
    }

    private function schedule(
        string $nik,
        string $offStart,
        string $realizationType = RosterSchedule::REALIZATION_PENDING,
        bool $isActive = true
    ): RosterSchedule {
        $this->seedRosterEmployee($nik, str_pad((string) (RosterSchedule::query()->count() + 1), 16, '0', STR_PAD_LEFT));

        return RosterSchedule::create([
            'employee_nik' => $nik,
            'period_year' => 2026,
            'period_number' => 1,
            'work_start' => Carbon::parse($offStart)->subDays(56)->toDateString(),
            'work_end' => Carbon::parse($offStart)->subDay()->toDateString(),
            'off_start' => $offStart,
            'off_end' => Carbon::parse($offStart)->addDays(13)->toDateString(),
            'earned_off_days' => 5,
            'realization_type' => $realizationType,
            'is_active' => $isActive,
        ]);
    }
}
