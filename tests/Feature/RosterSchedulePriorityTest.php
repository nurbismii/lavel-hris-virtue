<?php

namespace Tests\Feature;

use App\Models\RosterSchedule;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesRosterImportSchema;
use Tests\TestCase;

class RosterSchedulePriorityTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->cleanRosterImportFixtures();
        Schema::dropAllTables();
        DB::disconnect('sqlite');

        parent::tearDown();
    }

    public function test_priority_scope_orders_overdue_upcoming_and_completed_stably(): void
    {
        $completedPast = $this->schedule('001', '2026-08-28', RosterSchedule::REALIZATION_CUTI);
        $futureFar = $this->schedule('002', '2026-09-10');
        $overdueOld = $this->schedule('003', '2026-08-20');
        $today = $this->schedule('004', '2026-09-01');
        $overdueNearB = $this->schedule('006', '2026-08-31');
        $futureNear = $this->schedule('007', '2026-09-02');
        $overdueNearA = $this->schedule('005', '2026-08-31');
        $inactivePastPending = $this->schedule(
            '008',
            '2026-08-30',
            RosterSchedule::REALIZATION_PENDING,
            false
        );

        $ordered = RosterSchedule::query()
            ->priorityForToday(Carbon::today())
            ->pluck('employee_nik')
            ->all();

        $this->assertSame([
            $overdueNearA->employee_nik,
            $overdueNearB->employee_nik,
            $overdueOld->employee_nik,
            $today->employee_nik,
            $futureNear->employee_nik,
            $futureFar->employee_nik,
            $inactivePastPending->employee_nik,
            $completedPast->employee_nik,
        ], $ordered);
    }

    public function test_overdue_helper_requires_active_pending_schedule_before_today(): void
    {
        $this->assertTrue($this->schedule('010', '2026-08-31')->isOverduePending());
        $this->assertFalse($this->schedule('011', '2026-09-01')->isOverduePending());
        $this->assertFalse($this->schedule('012', '2026-08-31', RosterSchedule::REALIZATION_CUTI)->isOverduePending());
        $this->assertFalse($this->schedule('013', '2026-08-31', RosterSchedule::REALIZATION_PENDING, false)->isOverduePending());
    }

    public function test_manual_submission_migration_is_reversible_without_dropping_existing_table(): void
    {
        Schema::drop('roster_schedules');
        Schema::create('roster_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('employee_nik');
            $table->string('realization_type')->nullable();
            $table->date('off_start');
            $table->boolean('is_active')->default(true);
        });

        $migration = require database_path(
            'migrations/2026_08_28_000001_add_manual_submission_columns_to_roster_schedules.php'
        );
        $migration->up();

        $this->assertTrue(Schema::hasColumns('roster_schedules', [
            'manual_submitted_at',
            'manual_submitted_by',
            'manual_reference_number',
            'manual_submission_note',
        ]));
        $indexes = array_map(function ($index) {
            return $index->name;
        }, DB::select("PRAGMA index_list('roster_schedules')"));
        $this->assertContains('roster_schedules_priority_index', $indexes);
        $this->assertContains('roster_schedules_manual_submitter_index', $indexes);

        $migration->down();

        $this->assertTrue(Schema::hasTable('roster_schedules'));
        $this->assertTrue(Schema::hasColumns('roster_schedules', [
            'employee_nik',
            'realization_type',
            'off_start',
            'is_active',
        ]));
        $this->assertFalse(Schema::hasColumns('roster_schedules', [
            'manual_submitted_at',
            'manual_submitted_by',
            'manual_reference_number',
            'manual_submission_note',
        ]));
        $indexesAfterRollback = array_map(function ($index) {
            return $index->name;
        }, DB::select("PRAGMA index_list('roster_schedules')"));
        $this->assertNotContains('roster_schedules_priority_index', $indexesAfterRollback);
        $this->assertNotContains('roster_schedules_manual_submitter_index', $indexesAfterRollback);
    }

    private function schedule(
        string $suffix,
        string $offStart,
        string $realizationType = RosterSchedule::REALIZATION_PENDING,
        bool $isActive = true
    ): RosterSchedule {
        $nik = 'NIK' . $suffix;
        $this->seedRosterEmployee($nik, str_pad($suffix, 16, '0', STR_PAD_LEFT));

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
