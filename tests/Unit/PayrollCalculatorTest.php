<?php

namespace Tests\Unit;

use App\Services\Payroll\PayrollCalculator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayrollCalculatorTest extends TestCase
{
    private function input(array $changes=[]): array
    {
        return array_merge(['ptkp'=>'TK/0','skema_pajak'=>'gross_up','subjek_pajak'=>'DN_SETAHUN','fasilitas'=>'NORMAL','nik_npwp_valid'=>1,
            'cash_income'=>10000000,'cash_deductions'=>0,'wage_tk'=>10000000,'wage_health'=>10000000,'minimum_wage'=>4000000,
            'jkk_persen'=>0.24,'jht_active'=>1,'jp_active'=>1,'health_active'=>1,'risk_active'=>1,'extra_family'=>0,
            'last_period'=>0,'natura'=>0,'other_pension'=>0,'zakat'=>0],$changes);
    }

    public function test_ter_boundaries_all_categories_and_highest_band(): void
    {
        $service=new PayrollCalculator();
        foreach ([['A',5400000,0],['A',5400001,13500],['A',10050000,201000],['A',10050001,226125],['B',6200000,0],['B',6200001,15500],['B',9200000,92000],['B',9200001,138000],['C',6600000,0],['C',6600001,16500],['C',12050000,241000],['C',12050001,361500],['A',1400000001,476000000],['B',1405000001,477700000],['C',1419000001,482460000]] as [$category,$gross,$expected]) {
            $this->assertSame($expected,$service->monthly($gross,$category)['tax']);
        }
    }

    public function test_gross_up_includes_taxable_employer_premiums_but_not_employer_jht_or_jp(): void
    {
        $r=(new PayrollCalculator())->calculate($this->input(),'2026-01');
        $this->assertSame(323319,$r['tax_allowance']);
        $this->assertSame(323319,$r['pph21']);
        $this->assertSame(10777319,$r['tax_gross']);
        $this->assertSame(9600000,$r['take_home']);
        $this->assertSame(1024000,$r['employer_bpjs']);
        $this->assertSame(11347319,$r['employer_cost']);
    }

    public function test_gross_up_crosses_a_ter_jump_and_converges_in_full_rupiah(): void
    {
        $r=(new PayrollCalculator())->calculate($this->input(['jht_active'=>0,'jp_active'=>0,'health_active'=>0,'risk_active'=>0]),'2026-01');
        $this->assertSame(230179,$r['tax_allowance']);
        $this->assertSame(230179,$r['pph21']);
        $this->assertSame(10000000,$r['take_home']);
    }

    public function test_bpjs_caps_follow_month_and_health_has_floor_and_extra_family(): void
    {
        $service=new PayrollCalculator();
        $jan=$service->calculate($this->input(['wage_tk'=>20000000,'wage_health'=>20000000,'extra_family'=>2]),'2026-02');
        $mar=$service->calculate($this->input(['wage_tk'=>20000000,'wage_health'=>20000000]),'2026-03');
        $this->assertSame(105474,$jan['bpjs']['employee_jp']);
        $this->assertSame(110863,$mar['bpjs']['employee_jp']);
        $this->assertSame(221726,$mar['bpjs']['employer_jp']);
        $this->assertSame(120000,$jan['bpjs']['employee_health']);
        $this->assertSame(240000,$jan['bpjs']['extra_family']);
        $floor=$service->calculate($this->input(['wage_health'=>1000000]),'2026-01');
        $this->assertSame(4000000,$floor['bpjs']['health_basis']);
        $this->assertSame(40000,$floor['bpjs']['employee_health']);
    }

    public function test_last_period_matches_pp58_official_annual_example(): void
    {
        $r=(new PayrollCalculator())->calculate($this->input(['ptkp'=>'K/0','skema_pajak'=>'gross','jht_active'=>0,'jp_active'=>0,'health_active'=>0,'risk_active'=>0,
            'other_pension'=>100000,'cash_deductions'=>100000,'start_month'=>1,'ytd_months'=>11,'ytd_gross'=>110000000,'ytd_contributions'=>1100000,'ytd_zakat'=>0,'ytd_tax'=>2200000]),'2026-12');
        $this->assertSame('PASAL_17',$r['tax_detail']['method']);
        $this->assertSame(54300000,$r['tax_detail']['pkp']);
        $this->assertSame(2715000,$r['tax_detail']['tax']);
        $this->assertSame(515000,$r['pph21']);
    }

    public function test_last_period_refund_is_not_clamped_or_taken_back_as_negative_allowance(): void
    {
        $r=(new PayrollCalculator())->calculate($this->input(['cash_income'=>5000000,'jht_active'=>0,'jp_active'=>0,'health_active'=>0,'risk_active'=>0,
            'start_month'=>1,'ytd_months'=>11,'ytd_gross'=>55000000,'ytd_contributions'=>0,'ytd_zakat'=>0,'ytd_tax'=>1000000]),'2026-12');
        $this->assertSame(-850000,$r['pph21']);
        $this->assertSame(0,$r['tax_allowance']);
        $this->assertSame(5850000,$r['take_home']);
    }

    public function test_fractional_job_expense_is_not_rounded_before_pkp(): void
    {
        $r=(new PayrollCalculator())->calculate($this->input(['cash_income'=>16843157,'jht_active'=>0,'jp_active'=>0,'health_active'=>0,'risk_active'=>0,
            'start_month'=>1,'ytd_months'=>11,'ytd_gross'=>40000000,'ytd_contributions'=>0,'ytd_zakat'=>0,'ytd_tax'=>0]),'2026-12');
        $this->assertSame(2842157.85,$r['tax_detail']['job_expense']);
        $this->assertSame(0,$r['tax_detail']['pkp']);
        $this->assertSame(0,$r['pph21']);
    }

    public function test_gross_up_annual_reconciliation_converges_and_preserves_net_cash(): void
    {
        $r=(new PayrollCalculator())->calculate($this->input(['start_month'=>1,'ytd_months'=>11,'ytd_gross'=>118550509,'ytd_contributions'=>3300000,'ytd_zakat'=>0,'ytd_tax'=>3556509]),'2026-12');
        $this->assertSame($r['pph21'],$r['tax_allowance']);
        $this->assertGreaterThan(0,$r['pph21']);
        $this->assertSame(9600000,$r['take_home']);
        $this->assertSame(0,$r['tax_detail']['pkp']%1000);
    }

    public function test_missing_ytd_is_rejected_in_december_and_on_termination(): void
    {
        $this->expectException(ValidationException::class);
        (new PayrollCalculator())->calculate($this->input(['last_period'=>1]),'2026-05');
    }

    public function test_unknown_ptkp_and_unverified_future_rules_are_rejected(): void
    {
        foreach ([['ptkp'=>''],['fasilitas'=>'DTP'],['subjek_pajak'=>'WPLN'],['cash_income'=>-1],['wage_health'=>0],['jkk_persen'=>0.12],['nik_npwp_valid'=>0]] as $changes) {
            try { (new PayrollCalculator())->calculate($this->input($changes),'2026-01'); $this->fail('Invalid inputs were accepted.'); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
        $this->expectException(ValidationException::class);
        (new PayrollCalculator())->calculate($this->input(),'2027-01');
    }
}
