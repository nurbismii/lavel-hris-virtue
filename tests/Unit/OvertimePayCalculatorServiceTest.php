<?php

namespace Tests\Unit;

use App\Models\OvertimePayRule;
use App\Services\Overtime\OvertimePayCalculatorService;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Models\OvertimeOrder;
use App\Models\WorkPattern;

class OvertimePayCalculatorServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        (require database_path('migrations/2026_04_30_000001_create_overtime_pay_rules_table.php'))->up();
    }

    public function test_bracket_boundaries_fractional_hours_and_overflow(): void
    {
        $calculator = new OvertimePayCalculatorService();
        foreach ([
            ['five_two', 'workday', 30, 0.75], ['five_two', 'workday', 90, 2.5],
            ['five_two', 'off_or_holiday', 480, 16.0], ['five_two', 'off_or_holiday', 540, 19.0],
            ['five_two', 'off_or_holiday', 720, 31.0], ['six_one', 'off_or_holiday', 420, 14.0],
            ['six_one', 'off_or_holiday', 480, 17.0], ['six_one', 'off_or_holiday', 660, 29.0],
            ['six_one', 'shortest_workday_holiday', 300, 10.0], ['six_one', 'shortest_workday_holiday', 360, 13.0],
            ['six_one', 'shortest_workday_holiday', 540, 25.0],
        ] as [$schedule, $day, $minutes, $units]) {
            $result = $calculator->calculate($schedule, $day, $minutes, 1730000);
            $this->assertSame($units, $result['multiplier_units']);
            $this->assertSame($units * 10000, (float) $result['amount']);
        }
        $overflow = $calculator->calculate('five_two', 'off_or_holiday', 780, 1730000);
        $this->assertSame(60, $overflow['overflow_minutes']);
        $this->assertNotEmpty($overflow['warnings']);
        $this->assertNotEmpty($calculator->calculate('five_two', 'workday', 241, 1730000)['warnings']);
    }

    public function test_master_gaps_and_lower_rates_do_not_reduce_statutory_pay(): void
    {
        DB::table('overtime_pay_rules')->delete();
        OvertimePayRule::create(['code' => 'LOW', 'name' => 'Low', 'schedule_type' => 'any', 'day_type' => 'workday',
            'hour_from' => 1, 'hour_to' => 1, 'multiplier' => 1, 'is_active' => true, 'sort_order' => 1, 'legal_basis' => 'Company']);
        $result = (new OvertimePayCalculatorService())->calculate('five_two', 'workday', 180, 1730000);
        $this->assertSame(5.5, $result['multiplier_units']);
    }

    public function test_overlapping_company_rules_apply_highest_rate_only_once(): void
    {
        foreach ([2.5, 3.0] as $i => $rate) {
            OvertimePayRule::create(['code' => 'HIGH-' . $i, 'name' => 'Company benefit', 'schedule_type' => 'any', 'day_type' => 'workday',
                'hour_from' => 1, 'hour_to' => 3, 'multiplier' => $rate, 'is_active' => true, 'sort_order' => 1, 'legal_basis' => 'Company']);
        }
        $result = (new OvertimePayCalculatorService())->calculate('five_two', 'workday', 180, 1730000);
        $this->assertSame(9.0, $result['multiplier_units']);
        $this->assertSame(180, $result['covered_minutes']);
    }

    public function test_shortest_workday_uses_configured_sixth_day_and_only_official_holiday_type(): void
    {
        $pattern = new WorkPattern(['pattern_basis' => 'weekly', 'weekly_work_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
            'start_time' => '08:00', 'end_time' => '16:00', 'break_start_time' => '12:00', 'break_end_time' => '13:00',
            'sixth_day_start_time' => '08:00', 'sixth_day_end_time' => '13:00']);
        $order = new OvertimeOrder(['overtime_date' => '2027-01-02', 'overtime_type' => OvertimeOrder::TYPE_HOLIDAY, 'required_minutes' => 420]);
        $calculator = new OvertimePayCalculatorService();
        $this->assertTrue($calculator->isShortestWorkday($pattern, '2027-01-02'));
        $this->assertFalse($calculator->isShortestWorkday($pattern, '2027-01-01'));
        $this->assertSame(17.0, $calculator->calculateForOrder($order, 1730000, $pattern)['multiplier_units']);
        $order->overtime_type = OvertimeOrder::TYPE_OFF;
        $this->assertSame(14.0, $calculator->calculateForOrder($order, 1730000, $pattern)['multiplier_units']);
    }

    public function test_hr_export_preserves_smelter_minimum_and_adds_actual_hours_above_it(): void
    {
        $employee = new \App\Models\Employee(['nik' => '001', 'entry_date' => '2026-01-01']);
        $employee->setRelation('workPattern', new WorkPattern(['work_duration_value' => 5, 'off_duration_value' => 2]));
        $employee->setRelation('departemen', new \App\Models\Departemen(['departemen' => 'SMELTER']));
        $employee->setRelation('divisi', null);
        $service = app(\App\Services\Presensi\AttendanceHrSummaryExportService::class);
        $method = new \ReflectionMethod($service, 'summarizeEmployeeAttendance');
        $date = \Carbon\Carbon::parse('2027-01-02');
        $result = $method->invoke($service, $employee, $date, $date, ['2027-01-02' => true], [], [],
            ['2027-01-02' => ['gross_minutes' => 600, 'has_clock' => true]]);
        $this->assertSame(23.0, (float) $result['overtime_holiday']);
        $result = $method->invoke($service, $employee, $date, $date, ['2027-01-02' => true], [], [], []);
        $this->assertSame(16.0, (float) $result['overtime_holiday']);
    }

    public function test_workday_overtime_uses_first_hour_one_point_five_and_next_hours_double(): void
    {
        $result = (new OvertimePayCalculatorService())->calculate(
            OvertimePayRule::SCHEDULE_FIVE_TWO,
            OvertimePayRule::DAY_WORKDAY,
            180,
            1730000
        );

        $this->assertSame(5.5, $result['multiplier_units']);
        $this->assertSame(55000.0, (float) $result['amount']);
    }

    public function test_five_two_off_or_holiday_uses_two_three_four_multiplier_brackets(): void
    {
        $result = (new OvertimePayCalculatorService())->calculate(
            OvertimePayRule::SCHEDULE_FIVE_TWO,
            OvertimePayRule::DAY_OFF_OR_HOLIDAY,
            600,
            1730000
        );

        $this->assertSame(23.0, $result['multiplier_units']);
        $this->assertSame(230000.0, (float) $result['amount']);
    }

    public function test_six_one_off_or_holiday_uses_seven_eight_eleven_hour_brackets(): void
    {
        $result = (new OvertimePayCalculatorService())->calculate(
            OvertimePayRule::SCHEDULE_SIX_ONE,
            OvertimePayRule::DAY_OFF_OR_HOLIDAY,
            540,
            1730000
        );

        $this->assertSame(21.0, $result['multiplier_units']);
        $this->assertSame(210000.0, (float) $result['amount']);
    }

    public function test_six_one_shortest_workday_holiday_uses_shorter_brackets(): void
    {
        $result = (new OvertimePayCalculatorService())->calculate(
            OvertimePayRule::SCHEDULE_SIX_ONE,
            OvertimePayRule::DAY_SHORTEST_WORKDAY_HOLIDAY,
            420,
            1730000
        );

        $this->assertSame(17.0, $result['multiplier_units']);
        $this->assertSame(170000.0, (float) $result['amount']);
    }
}
