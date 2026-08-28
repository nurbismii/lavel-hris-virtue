<?php

namespace Tests\Unit;

use App\Models\RosterScheduleHistory;
use App\Services\Roster\RosterHistoryRemarkParser;
use PHPUnit\Framework\TestCase;

class RosterHistoryRemarkParserTest extends TestCase
{
    private RosterHistoryRemarkParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new RosterHistoryRemarkParser();
    }

    public function test_it_classifies_explicit_incentive_and_leave_per_period(): void
    {
        $remark = 'I. AMBIL INSENTIF DI PRD MARET 2024, II. AMBIL CUTI DI PRD MEI 2024';

        $periodOne = $this->parser->parse($remark, 1);
        $periodTwo = $this->parser->parse($remark, 2);

        $this->assertSame(RosterScheduleHistory::CLASSIFICATION_INSENTIF, $periodOne['classification']);
        $this->assertSame(RosterScheduleHistory::CLASSIFICATION_CUTI, $periodTwo['classification']);
        $this->assertSame(RosterScheduleHistory::REVIEW_NOT_REQUIRED, $periodOne['review_status']);
    }

    public function test_ambiguous_taken_remark_requires_hr_review(): void
    {
        $result = $this->parser->parse('III&IV. AMBIL DI PRD DESEMBER 2024', 3);

        $this->assertSame(RosterScheduleHistory::CLASSIFICATION_NEED_REVIEW, $result['classification']);
        $this->assertSame(RosterScheduleHistory::REVIEW_PENDING, $result['review_status']);
        $this->assertStringContainsString('AMBIL', $result['remark_segment']);
    }

    public function test_global_non_roster_remark_is_not_applied_as_leave(): void
    {
        $result = $this->parser->parse('KETENTUAN PROMOSINYA TIDAK MENGIKUT ROSTER', 2);

        $this->assertSame(RosterScheduleHistory::CLASSIFICATION_NOT_APPLICABLE, $result['classification']);
        $this->assertSame(RosterScheduleHistory::REVIEW_NOT_REQUIRED, $result['review_status']);
    }

    public function test_period_without_specific_remark_remains_planned(): void
    {
        $result = $this->parser->parse('IV. AMBIL INSENTIF DI PRD NOVEMBER', 1);

        $this->assertSame(RosterScheduleHistory::CLASSIFICATION_PLANNED, $result['classification']);
        $this->assertNull($result['remark_segment']);
    }
}
