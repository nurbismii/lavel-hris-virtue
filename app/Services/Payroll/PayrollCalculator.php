<?php

namespace App\Services\Payroll;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PayrollCalculator
{
    public function calculate(array $input, string $month): array
    {
        $rules = [
            'ptkp' => ['required', Rule::in(array_keys(config('payroll.ptkp')))],
            'skema_pajak' => ['required', Rule::in(['gross_up', 'gross'])],
            'subjek_pajak' => ['required', Rule::in(['DN_SETAHUN'])],
            'fasilitas' => ['required', Rule::in(['NORMAL'])],
            'nik_npwp_valid' => ['required', 'accepted'],
            'jkk_persen' => ['required', 'numeric', Rule::in(array_map(function ($rate) { return $rate/100; },config('payroll.jkk_rates')))],
        ];
        foreach (['cash_income','wage_tk','wage_health','minimum_wage','cash_deductions','natura','other_pension','zakat'] as $key) {
            $rules[$key] = ['required','integer','min:0','max:100000000000'];
        }
        foreach (['jht_active','jp_active','health_active','risk_active','last_period'] as $key) {
            $rules[$key] = ['required','integer','in:0,1'];
        }
        $rules['extra_family'] = ['required','integer','min:0','max:10'];
        $labels=['wage_tk'=>'upah_bpjs_tk','wage_health'=>'upah_bpjs_kes','minimum_wage'=>'upah_minimum',
            'jht_active'=>'jht_aktif','jp_active'=>'jp_aktif','health_active'=>'kes_aktif','risk_active'=>'jkk_jkm_aktif',
            'extra_family'=>'keluarga_tambahan','last_period'=>'masa_terakhir','other_pension'=>'iuran_pensiun_lain',
            'start_month'=>'bulan_mulai_pajak','ytd_months'=>'ytd_bulan','ytd_gross'=>'ytd_bruto','ytd_contributions'=>'ytd_iuran','ytd_tax'=>'ytd_pph21',
            'cash_income'=>'penghasilan kas','cash_deductions'=>'potongan AC','nik_npwp_valid'=>'verifikasi NIK/NPWP'];
        $messages=['required'=>':attribute wajib diisi; isi 0 jika memang nihil.','integer'=>':attribute harus angka bulat.',
            'numeric'=>':attribute harus angka.','in'=>':attribute tidak sesuai pilihan pada PANDUAN.',
            'min'=>':attribute kurang dari batas yang diperbolehkan.','max'=>':attribute melebihi batas yang diperbolehkan.',
            'accepted'=>':attribute harus dikonfirmasi setelah identitas pajak dinyatakan valid/terintegrasi DJP.'];
        Validator::make($input, $rules, $messages, $labels)
            ->validate();
        if ($input['cash_deductions'] < $input['other_pension'] + $input['zakat']) {
            $this->invalid('AC harus sudah mencakup iuran pensiun lain dan zakat yang dipotong dari kas. Parameter tersebut tidak ditambahkan lagi agar tidak terjadi potongan ganda.');
        }
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
            || $month < config('payroll.valid_from') || $month > config('payroll.valid_through')) {
            $this->invalid('Peraturan payroll baru diverifikasi untuk masa pajak tahun 2026.');
        }
        $monthNumber = (int) substr($month, 5, 2);
        $last = $monthNumber === 12 || (bool) $input['last_period'];
        if ($last) {
            $ytdRules = ['start_month'=>['required','integer','min:1','max:'.$monthNumber]];
            foreach (['ytd_gross','ytd_contributions','ytd_zakat','ytd_tax'] as $key) {
                $ytdRules[$key] = ['required','integer','min:0','max:1000000000000'];
            }
            $ytdRules['ytd_months'] = ['required','integer','min:0','max:11'];
            Validator::make($input, $ytdRules, $messages, $labels)->validate();
            if ((int) $input['ytd_months'] !== $monthNumber - (int) $input['start_month']) {
                $this->invalid('Jumlah bulan YTD harus mencakup semua bulan sejak mulai bekerja pada pemberi kerja ini sampai bulan sebelum masa pajak ini.');
            }
            if (!$input['ytd_months'] && array_sum(array_intersect_key($input, array_flip(['ytd_gross','ytd_contributions','ytd_zakat','ytd_tax'])))) {
                $this->invalid('Saldo YTD harus nihil bila belum ada bulan sebelumnya.');
            }
            if ($input['ytd_contributions'] + $input['ytd_zakat'] > $input['ytd_gross']) {
                $this->invalid('Pengurang YTD tidak boleh melebihi bruto YTD.');
            }
        }
        if (($input['jht_active'] || $input['jp_active'] || $input['risk_active']) && $input['wage_tk'] < 1) {
            $this->invalid('Dasar upah BPJS TK wajib lebih dari nol untuk program aktif.');
        }
        if ($input['health_active'] && ($input['wage_health'] < 1 || $input['minimum_wage'] < 1)) {
            $this->invalid('Isi dasar upah BPJS Kesehatan dan UMK/UMP yang berlaku.');
        }
        if (!$input['health_active'] && $input['extra_family']) {
            $this->invalid('Anggota keluarga tambahan memerlukan kepesertaan BPJS Kesehatan aktif.');
        }
        $jpCap = 0;
        foreach (config('payroll.jp_caps') as $from => $cap) {
            if ($from <= $month) { $jpCap = $cap; }
        }
        $tk = (int) $input['wage_tk'];
        $health = $input['health_active'] ? min(max((int) $input['wage_health'], (int) $input['minimum_wage']), config('payroll.health_cap')) : 0;
        $bpjs = [
            'health_basis'=>$health, 'tk_basis'=>$tk, 'jp_basis'=>min($tk, $jpCap), 'jp_cap'=>$jpCap,
            'employee_health'=>$this->contribution($health, 100),
            'extra_family'=>$this->contribution($health, 100 * (int) $input['extra_family']),
            'employer_health'=>$this->contribution($health, 400),
            'employee_jht'=>$input['jht_active'] ? $this->contribution($tk, 200) : 0,
            'employer_jht'=>$input['jht_active'] ? $this->contribution($tk, 370) : 0,
            'employee_jp'=>$input['jp_active'] ? $this->contribution(min($tk, $jpCap), 100) : 0,
            'employer_jp'=>$input['jp_active'] ? $this->contribution(min($tk, $jpCap), 200) : 0,
            'employer_jkk'=>$input['risk_active'] ? $this->contribution($tk, (int) round($input['jkk_persen'] * 100)) : 0,
            'employer_jkm'=>$input['risk_active'] ? $this->contribution($tk, 30) : 0,
        ];
        $employeeBpjs = $bpjs['employee_health'] + $bpjs['extra_family'] + $bpjs['employee_jht'] + $bpjs['employee_jp'];
        $employerBpjs = $bpjs['employer_health'] + $bpjs['employer_jht'] + $bpjs['employer_jp'] + $bpjs['employer_jkk'] + $bpjs['employer_jkm'];
        // Unpaid leave/alpa reduce earned taxable wages; other cash deductions do not.
        $gross = (int) $input['cash_income'] + (int) $input['natura'] + $bpjs['employer_health'] + $bpjs['employer_jkk'] + $bpjs['employer_jkm'];
        $deductible = $bpjs['employee_jht'] + $bpjs['employee_jp'] + (int) $input['other_pension'];
        $category = config('payroll.categories.'.$input['ptkp']);
        $taxFor = function (int $allowance) use ($input, $gross, $deductible, $last, $category) {
            return $last
                ? $this->annual($gross + $allowance, $deductible, $input)['tax'] - (int) $input['ytd_tax']
                : $this->monthly($gross + $allowance, $category)['tax'];
        };
        $allowance = 0;
        if ($input['skema_pajak'] === 'gross_up') {
            // Start at zero: monotone fixed-point iteration picks the smallest consistent allowance, including at TER jumps.
            for ($i = 0; $i < 200; $i++) {
                $next = max(0, $taxFor($allowance));
                if ($next === $allowance) { break; }
                $allowance = $next;
            }
            if ($i === 200) { $this->invalid('Gross-up belum konvergen; periksa komponen dan saldo pajak.'); }
        }
        $tax = $taxFor($allowance);
        $takeHome = (int) $input['cash_income'] + $allowance - (int) $input['cash_deductions'] - $employeeBpjs - $tax;
        if ($takeHome < 0) { $this->invalid('Total potongan melebihi penghasilan. Periksa komponen sebelum finalisasi.'); }
        return [
            'rules_version'=>config('payroll.rules_version'), 'tax_period'=>$month,
            'last_period'=>$last, 'category'=>$category, 'tax_allowance'=>$allowance,
            'tax_gross'=>$gross + $allowance, 'pph21'=>$tax,
            'tax_detail'=>$last ? $this->annual($gross + $allowance, $deductible, $input) : $this->monthly($gross + $allowance, $category),
            'deductible_contributions'=>$deductible, 'bpjs'=>$bpjs,
            'employee_bpjs'=>$employeeBpjs, 'employer_bpjs'=>$employerBpjs,
            'take_home'=>$takeHome, 'employer_cost'=>(int) $input['cash_income'] + $allowance + $employerBpjs,
        ];
    }

    public function monthly(int $gross, string $category): array
    {
        foreach (config('payroll.ter.'.$category, []) as [$upper, $rate]) {
            if ($upper === null || $gross <= $upper) {
                return ['method'=>'TER', 'rate_bps'=>$rate, 'tax'=>intdiv($gross * $rate, 10000)];
            }
        }
        throw new \InvalidArgumentException('Kategori TER tidak dikenal.');
    }

    private function annual(int $gross, int $deductible, array $input): array
    {
        $annualGross = $gross + (int) $input['ytd_gross'];
        // Keep the 5% expense in cents: rounding it early can shift PKP across a Rp1,000 boundary.
        $expenseCents = min($annualGross * 5, ((int) $input['ytd_months'] + 1) * 50000000);
        $netCents = max(0, $annualGross * 100 - $expenseCents - ($deductible + (int) $input['ytd_contributions'] + (int) $input['zakat'] + (int) $input['ytd_zakat']) * 100);
        $pkp = intdiv(max(0, $netCents - config('payroll.ptkp.'.$input['ptkp']) * 100), 100000) * 1000;
        $remaining = $pkp;
        $tax = 0;
        foreach ([[60000000,500],[190000000,1500],[250000000,2500],[4500000000,3000],[PHP_INT_MAX,3500]] as [$width,$rate]) {
            $part = min($remaining, $width);
            $tax += intdiv($part * $rate, 10000);
            $remaining -= $part;
        }
        return ['method'=>'PASAL_17','annual_gross'=>$annualGross,'job_expense'=>$expenseCents/100,'annual_net'=>$netCents/100,'pkp'=>$pkp,'tax'=>$tax,'previous_tax'=>(int) $input['ytd_tax']];
    }

    private function contribution(int $basis, int $rate): int
    {
        return intdiv($basis * $rate + 5000, 10000);
    }

    private function invalid(string $message): void
    {
        throw ValidationException::withMessages(['payroll'=>$message]);
    }
}
