<?php

namespace Tests\Unit;

use App\Models\OvertimePayRule;
use App\Services\Overtime\OvertimePayCalculatorService;
use Tests\TestCase;

class OvertimePayCalculatorServiceTest extends TestCase
{
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
