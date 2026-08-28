<?php

namespace Tests\Feature;

use App\Models\RosterSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
