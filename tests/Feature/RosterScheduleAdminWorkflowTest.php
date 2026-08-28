<?php

namespace Tests\Feature;

use App\Jobs\SendRosterScheduleReminder;
use App\Models\RosterSchedule;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use Carbon\Carbon;
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
        $this->assertCount(1, $audit->records);
        $this->assertSame('roster_schedule.overdue_reminder_queued', $audit->records[0]['event']);
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

    private function fakeAudit(): AuditTrailService
    {
        $audit = new class extends AuditTrailService {
            public array $records = [];

            public function record(array $data): ?\App\Models\AuditTrail
            {
                $this->records[] = $data;

                return null;
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
