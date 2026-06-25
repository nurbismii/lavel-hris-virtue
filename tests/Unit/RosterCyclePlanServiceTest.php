<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\WorkPattern;
use App\Services\Presensi\RosterCyclePlanService;
use Carbon\Carbon;
use Tests\TestCase;

class RosterCyclePlanServiceTest extends TestCase
{
    public function test_week_based_cycle_pattern_detects_off_segment(): void
    {
        $service = new RosterCyclePlanService();
        $employee = $this->employeeWithPattern(8, 2, '2026-01-01');

        $this->assertFalse($service->isDateInRosterOffSegment($employee, '2026-02-25'));
        $this->assertTrue($service->isDateInRosterOffSegment($employee, '2026-02-26'));
        $this->assertTrue($service->isDateInRosterOffSegment($employee, '2026-03-11'));
        $this->assertFalse($service->isDateInRosterOffSegment($employee, '2026-03-12'));
    }

    public function test_reminder_cycle_uses_configured_work_and_off_weeks(): void
    {
        $service = new RosterCyclePlanService();
        $employee = $this->employeeWithPattern(8, 2, '2026-01-01');

        $cycle = $service->reminderCycleFor($employee, 3, Carbon::parse('2026-02-22'));

        $this->assertNotNull($cycle);
        $this->assertSame('2026-01-01', $cycle['work_start']->toDateString());
        $this->assertSame('2026-02-25', $cycle['work_end']->toDateString());
        $this->assertSame('2026-02-26', $cycle['off_start']->toDateString());
        $this->assertSame('2026-03-11', $cycle['off_end']->toDateString());
        $this->assertSame(8, $cycle['work_weeks']);
        $this->assertSame(2, $cycle['off_weeks']);
    }

    public function test_non_week_cycle_pattern_is_not_roster_cycle(): void
    {
        $service = new RosterCyclePlanService();
        $pattern = new WorkPattern([
            'pattern_basis' => WorkPattern::BASIS_CYCLE,
            'work_duration_value' => 5,
            'work_duration_unit' => WorkPattern::UNIT_DAY,
            'off_duration_value' => 2,
            'off_duration_unit' => WorkPattern::UNIT_DAY,
        ]);

        $this->assertFalse($service->isRosterCyclePattern($pattern));
    }

    private function employeeWithPattern(int $workWeeks, int $offWeeks, string $startDate): Employee
    {
        $pattern = new WorkPattern([
            'pattern_basis' => WorkPattern::BASIS_CYCLE,
            'work_duration_value' => $workWeeks,
            'work_duration_unit' => WorkPattern::UNIT_WEEK,
            'off_duration_value' => $offWeeks,
            'off_duration_unit' => WorkPattern::UNIT_WEEK,
        ]);

        $employee = new Employee([
            'nik' => 'EMP001',
            'work_pattern_start_date' => $startDate,
        ]);
        $employee->setRelation('workPattern', $pattern);

        return $employee;
    }
}
