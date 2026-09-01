<?php

namespace Tests\Feature;

use App\Models\RosterScheduleHistory;
use App\Models\RosterSchedule;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesRosterImportSchema;
use Tests\TestCase;

class RosterScheduleEmployeeHistoryTest extends TestCase
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

        $this->createRosterImportSchema();
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('email_verified_at')->nullable();
        });
        Schema::table('cuti_roster', function (Blueprint $table): void {
            $table->unsignedTinyInteger('status_pengajuan')->nullable();
            $table->unsignedTinyInteger('status_pengajuan_hrd')->nullable();
        });
    }

    protected function tearDown(): void
    {
        $this->cleanRosterImportFixtures();
        Schema::dropAllTables();
        DB::disconnect('sqlite');

        parent::tearDown();
    }

    public function test_staff_roster_only_sees_own_schedule_history(): void
    {
        $user = $this->makeUser('staff-history', 'EMP001', 'Staff Roster', ['roster']);
        $other = $this->makeUser('other-history', 'EMP002', 'Staff Roster', ['roster']);

        $this->makeHistory($user->nik_karyawan, 'Riwayat milik sendiri');
        $this->makeHistory($other->nik_karyawan, 'Riwayat karyawan lain');

        $this->actingAs($user)
            ->get(route('roster.history'))
            ->assertOk()
            ->assertSee('Riwayat milik sendiri')
            ->assertDontSee('Riwayat karyawan lain')
            ->assertDontSee('roster-import-internal.xlsx');
    }

    public function test_staff_roster_can_filter_own_history_by_year_and_classification(): void
    {
        $user = $this->makeUser('staff-filter', 'EMP003', 'Staff Roster', ['roster']);

        $this->makeHistory($user->nik_karyawan, 'Cuti 2025', [
            'period_year' => 2025,
            'classification' => RosterScheduleHistory::CLASSIFICATION_CUTI,
        ]);
        $this->makeHistory($user->nik_karyawan, 'Insentif 2026', [
            'period_year' => 2026,
            'classification' => RosterScheduleHistory::CLASSIFICATION_INSENTIF,
        ]);

        $this->actingAs($user)
            ->get(route('roster.history', [
                'year' => 2025,
                'classification' => RosterScheduleHistory::CLASSIFICATION_CUTI,
            ]))
            ->assertOk()
            ->assertSee('Cuti 2025')
            ->assertDontSee('Insentif 2026');
    }

    public function test_non_roster_role_cannot_open_employee_roster_history(): void
    {
        $user = $this->makeUser('regular-user', 'EMP004', 'Karyawan', ['roster']);

        $this->actingAs($user)
            ->get(route('roster.history'))
            ->assertForbidden();
    }

    public function test_staff_roster_sees_only_own_active_upcoming_schedules(): void
    {
        $user = $this->makeUser('staff-upcoming', 'EMP006', 'Staff Roster', ['roster']);
        $other = $this->makeUser('other-upcoming', 'EMP007', 'Staff Roster', ['roster']);
        $ownUpcoming = $this->makeSchedule($user->nik_karyawan, now()->addDays(10)->toDateString());
        $otherUpcoming = $this->makeSchedule($other->nik_karyawan, now()->addDays(20)->toDateString());
        $this->makeSchedule($user->nik_karyawan, now()->subDays(10)->toDateString());
        $this->makeSchedule($user->nik_karyawan, now()->addDays(30)->toDateString(), ['is_active' => false]);

        $this->actingAs($user)
            ->get(route('roster.history'))
            ->assertOk()
            ->assertSee($ownUpcoming->off_start->format('d M Y'))
            ->assertSee(route('roster.create', ['roster_schedule' => $ownUpcoming->id]), false)
            ->assertDontSee($otherUpcoming->off_start->format('d M Y'));
    }

    public function test_upcoming_schedule_with_active_application_is_not_offered_for_resubmission(): void
    {
        $user = $this->makeUser('staff-submitted', 'EMP008', 'Staff Roster', ['roster']);
        $schedule = $this->makeSchedule($user->nik_karyawan, now()->addDays(10)->toDateString());

        DB::table('cuti_roster')->insert([
            'roster_schedule_id' => $schedule->id,
            'status_pengajuan' => 0,
            'status_pengajuan_hrd' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('roster.history'))
            ->assertOk()
            ->assertSee(__('self_service.roster.upcoming_schedule_in_process'))
            ->assertDontSee(route('roster.create', ['roster_schedule' => $schedule->id]), false);
    }

    public function test_repeated_role_checks_only_inspect_additional_role_table_once_per_user(): void
    {
        $user = $this->makeUser('role-cache', 'EMP005', 'Staff Roster', ['roster']);
        $freshUser = User::query()->findOrFail($user->id);
        $queries = [];

        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        for ($iteration = 0; $iteration < 20; $iteration++) {
            $this->assertTrue($freshUser->hasRole('Staff Roster'));
            $this->assertTrue($freshUser->hasMenuAccess('roster'));
        }

        $roleTableSchemaChecks = collect($queries)->filter(function (string $sql): bool {
            return str_contains($sql, 'role_user')
                && (str_contains($sql, 'sqlite_master') || str_contains($sql, 'information_schema'));
        });

        $this->assertCount(1, $roleTableSchemaChecks);
    }

    private function makeUser(string $id, string $nik, string $role, array $menus): User
    {
        $this->seedRosterEmployee($nik, str_pad($nik, 16, '0', STR_PAD_LEFT), 'Karyawan ' . $nik);
        $roleId = DB::table('roles')->insertGetId([
            'permission_role' => $role,
            'menu_permissions' => json_encode($menus),
        ]);

        return User::create([
            'id' => $id,
            'name' => 'Karyawan ' . $nik,
            'email' => $id . '@example.test',
            'email_verified_at' => now(),
            'role_id' => $roleId,
            'nik_karyawan' => $nik,
        ]);
    }

    private function makeHistory(string $nik, string $remark, array $overrides = []): RosterScheduleHistory
    {
        return RosterScheduleHistory::create(array_merge([
            'employee_nik' => $nik,
            'period_year' => 2026,
            'period_number' => 1,
            'scheduled_off_start' => '2026-09-10',
            'scheduled_off_end' => '2026-09-14',
            'classification' => RosterScheduleHistory::CLASSIFICATION_PLANNED,
            'review_status' => RosterScheduleHistory::REVIEW_NOT_REQUIRED,
            'remark_segment' => $remark,
            'source_file' => 'roster-import-internal.xlsx',
            'imported_at' => now(),
        ], $overrides));
    }

    private function makeSchedule(string $nik, string $offStart, array $overrides = []): RosterSchedule
    {
        return RosterSchedule::query()->create(array_merge([
            'employee_nik' => $nik,
            'period_year' => (int) now()->year,
            'period_number' => 1,
            'work_start' => now()->subDays(50)->toDateString(),
            'work_end' => now()->addDays(9)->toDateString(),
            'off_start' => $offStart,
            'off_end' => date('Y-m-d', strtotime($offStart . ' +13 days')),
            'realization_type' => RosterSchedule::REALIZATION_PENDING,
            'is_active' => true,
        ], $overrides));
    }
}
