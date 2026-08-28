<?php

namespace Tests\Unit;

use App\Services\Roster\RosterScheduleService;
use Carbon\Carbon;
use Tests\TestCase;

class RosterScheduleServiceTest extends TestCase
{
    public function test_preview_uses_ten_work_weeks_and_two_off_weeks(): void
    {
        config()->set('roster.work_weeks', 10);
        config()->set('roster.off_weeks', 2);

        $cycles = app(RosterScheduleService::class)->previewCycles(Carbon::parse('2026-01-01'), 2);

        $this->assertCount(2, $cycles);
        $this->assertSame('2026-01-01', $cycles[0]['work_start']->toDateString());
        $this->assertSame('2026-03-11', $cycles[0]['work_end']->toDateString());
        $this->assertSame('2026-03-12', $cycles[0]['off_start']->toDateString());
        $this->assertSame('2026-03-25', $cycles[0]['off_end']->toDateString());
        $this->assertSame('2026-03-26', $cycles[1]['work_start']->toDateString());
        $this->assertSame('2026-06-17', $cycles[1]['off_end']->toDateString());
    }

    public function test_preview_limits_generation_to_sixty_cycles(): void
    {
        $cycles = app(RosterScheduleService::class)->previewCycles(Carbon::parse('2026-01-01'), 100);

        $this->assertCount(60, $cycles);
    }

    public function test_preview_until_does_not_generate_past_horizon(): void
    {
        config()->set('roster.work_weeks', 10);
        config()->set('roster.off_weeks', 2);

        $cycles = app(RosterScheduleService::class)->previewCyclesUntil(
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-12-31')
        );

        $this->assertCount(4, $cycles);
        $this->assertSame('2026-11-19', $cycles->last()['off_start']->toDateString());
        $this->assertTrue($cycles->last()['off_end']->lte(Carbon::parse('2026-12-31')));
    }
}
