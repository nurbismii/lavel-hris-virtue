<?php

namespace Tests\Unit;

use App\Services\Severance\SeveranceCalculator;
use App\Services\Severance\SeveranceResultFormatter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SeveranceCalculatorTest extends TestCase
{
    private function input(array $changes = []): array
    {
        return array_replace(['article' => '52(1)', 'calculation_start' => '2016-11-16', 'termination_date' => '2026-11-15',
            'salary' => ['basic_salary' => 4000000, 'position_allowance' => 500000, 'tenure_allowance' => 300000],
            'leave_days' => 12, 'return_fare' => 30000], $changes);
    }

    public function test_severance_and_all_service_award_boundaries_use_full_numeric_tenure(): void
    {
        $calculator = new SeveranceCalculator();
        foreach ([0 => 0, 1 => 0, 2 => 0, 3 => 2, 5 => 2, 6 => 3, 8 => 3, 9 => 4, 11 => 4, 12 => 5,
            14 => 5, 15 => 6, 18 => 7, 21 => 8, 23 => 8, 24 => 10, 30 => 10] as $years => $award) {
            $result = $calculator->calculate($this->input(['article' => '40', 'calculation_start' => (2026 - $years) . '-01-01',
                'termination_date' => '2026-01-01', 'leave_days' => 0, 'return_fare' => 0]));
            $lines = array_column($result['lines'], 'amount', 'code');
            $this->assertEquals(min($years + 1, 9) * 4800000, $lines['severance']);
            $this->assertEquals($award * 4800000, $lines['service_award'], 'Tenure: ' . $years);
        }
    }

    public function test_article_multipliers_and_basic_salary_leave_basis_match_confirmed_rules(): void
    {
        $calculator = new SeveranceCalculator();
        foreach (['52(1)' => 0.5, '55' => 2, '56' => 1.75] as $article => $multiplier) {
            $result = $calculator->calculate($this->input(['article' => $article]));
            $lines = array_column($result['lines'], 'amount', 'code');
            $this->assertEquals(9 * 4800000 * $multiplier, $lines['severance']);
            $this->assertEquals(1920000, $lines['leave']);
            $this->assertEquals(19200000, $lines['service_award']);
            $this->assertEquals(array_sum($lines), $result['total']);
        }
    }

    public function test_company_separation_thresholds_use_master_tenure_allowance_and_pkwt_has_no_two_digit_truncation(): void
    {
        $calculator = new SeveranceCalculator();
        foreach ([['50', 100000, 0], ['50', 150000, 1.5], ['50', 300000, 2.5], ['51', 100000, 0],
            ['51', 150000, 0.25], ['52(2)', 0, 0.25]] as [$article, $tenure, $factor]) {
            $input = $this->input(['article' => $article]); $input['salary']['tenure_allowance'] = $tenure;
            $lines = array_column($calculator->calculate($input)['lines'], 'amount', 'code');
            $this->assertEquals(4000000 * $factor, $lines['separation']);
        }
        $result = $calculator->calculate($this->input(['article' => '16']));
        $this->assertSame(120, $result['total_months']);
        $this->assertEquals(48000000, $result['total']);
        $this->assertCount(1, $result['lines']);
    }

    public function test_missing_leave_invalid_money_and_invalid_dates_cannot_silently_become_zero(): void
    {
        foreach ([['leave_days' => null], ['leave_days' => -1], ['leave_days' => 367], ['return_fare' => '1,000'],
            ['termination_date' => '2015-01-01'], ['article' => '99'],
            ['article' => '16', 'calculation_start' => '2026-11-15']] as $changes) {
            try {
                (new SeveranceCalculator())->calculate($this->input($changes));
                $this->fail('Invalid calculation was accepted.');
            } catch (ValidationException $exception) { $this->assertNotEmpty($exception->errors()); }
        }
        $zero = (new SeveranceCalculator())->calculate($this->input(['leave_days' => 0]));
        $this->assertEquals(0, array_column($zero['lines'], 'amount', 'code')['leave']);
    }

    public function test_master_sections_format_existing_results_without_changing_calculations(): void
    {
        foreach ([
            '52(1)' => ['A. PESANGON', 'B. PENGHARGAAN MASA KERJA', 'C. PENGGANTIAN HAK CUTI', 'D. ONGKOS PULANG', 'E. TOTAL'],
            '50' => ['A. UANG PISAH', 'C. PENGGANTIAN HAK CUTI', 'D. ONGKOS PULANG', 'E. TOTAL'],
            '16' => ['A. KOMPENSASI', 'B. TOTAL'],
        ] as $article => $labels) {
            $result = (new SeveranceCalculator())->calculate($this->input(['article' => (string) $article]));
            $snapshot = json_encode($result);
            $display = (new SeveranceResultFormatter())->format($result);
            $this->assertSame($labels, array_merge(array_column($display['lines'], 'label'), [$display['total_label']]));
            $this->assertSame($snapshot, json_encode($result));
            $this->assertSame($result['total'], $display['total']);
            foreach ($result['lines'] as $index => $line) {
                $this->assertSame($line['amount'], $display['lines'][$index]['amount']);
                $this->assertSame($line['formula'], $display['lines'][$index]['formula']);
            }
            $this->assertSame($display, (new SeveranceResultFormatter())->format($display));
        }
    }
}
