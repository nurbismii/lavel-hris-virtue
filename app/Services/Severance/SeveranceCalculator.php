<?php

namespace App\Services\Severance;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class SeveranceCalculator
{
    public function calculate(array $input): array
    {
        $article = (string) $input['article'];
        if (!array_key_exists($article, config('severance.articles'))) {
            $this->invalid('Pasal belum didukung. Pilih pasal dari daftar.');
        }
        $start = CarbonImmutable::parse($input['calculation_start'])->startOfDay();
        $end = CarbonImmutable::parse($input['termination_date'])->startOfDay();
        if ($end->lt($start)) { $this->invalid('Tanggal akhir kerja mendahului awal perhitungan.'); }
        $interval = $start->diff($end->addDay());
        $years = $interval->y;
        $months = $years * 12 + $interval->m;
        $salary = $input['salary'];
        $basic = $this->cents($salary['basic_salary']);
        $tenure = $this->cents($salary['tenure_allowance']);
        $position = $this->cents($salary['position_allowance']);
        $wage = $basic + $tenure + $position;
        if ($basic <= 0 || !isset($input['leave_days']) || !is_numeric($input['leave_days'])
            || $input['leave_days'] < 0 || $input['leave_days'] > 366) {
            $this->invalid('Gaji pokok harus positif dan sisa cuti wajib diisi 0–366 hari.');
        }
        $fare = $this->cents($input['return_fare']);
        $units = min($years + 1, 9);
        $awardUnits = $years < 3 ? 0 : ($years >= 24 ? 10 : intdiv($years, 3) + 1);
        $multiplier = ['40' => 1, '52(1)' => 0.5, '55' => 2, '56' => 1.75][$article] ?? 0;
        $lines = [];
        if ($article === '16') {
            if ($months < 1) { $this->invalid('Kompensasi PKWT memerlukan masa kerja paling sedikit satu bulan.'); }
            // shortcut: master memakai bulan penuh; tinjau sisa hari sebelum menyetujui kompensasi PKWT.
            $lines[] = $this->line('compensation', 'Kompensasi PKWT', $months . ' / 12 × total upah', $wage * $months / 12);
        } elseif (in_array($article, ['50', '51', '52(2)'], true)) {
            // Besaran uang pisah ini kebijakan master perusahaan, bukan pengali dari PP 35/2021.
            $tenureUnits = $tenure / 5000000;
            $separation = $article === '50' ? ($tenureUnits >= 6 ? 2.5 : ($tenureUnits >= 3 ? 1.5 : 0))
                : ($article === '51' ? ($tenureUnits >= 3 ? 0.25 : 0) : 0.25);
            $lines[] = $this->line('separation', 'Uang pisah', $separation . ' × gaji pokok', $basic * $separation);
        } else {
            $lines[] = $this->line('severance', 'Pesangon', $units . ' × ' . $multiplier . ' × total upah', $wage * $units * $multiplier);
            $lines[] = $this->line('service_award', 'Penghargaan masa kerja', $awardUnits . ' × total upah', $wage * $awardUnits);
        }
        if ($article !== '16') {
            $lines[] = $this->line('leave', 'Penggantian hak cuti', $input['leave_days'] . ' / 25 × gaji pokok', $basic * (float) $input['leave_days'] / 25);
            $lines[] = $this->line('return_fare', 'Ongkos pulang', 'Sesuai pengajuan yang diperiksa HI', $fare);
        }
        return [
            'rules_version' => config('severance.rules_version'), 'article' => $article,
            'years' => $years, 'months' => $interval->m, 'days' => $interval->d, 'total_months' => $months,
            'wage' => $wage / 100, 'leave_days' => (float) $input['leave_days'], 'lines' => $lines,
            'total' => array_sum(array_column($lines, 'amount_cents')) / 100,
        ];
    }

    public function cents($value): int
    {
        if (!is_scalar($value) || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', (string) $value)) {
            $this->invalid('Komponen upah/ongkos harus angka nonnegatif dengan maksimal dua desimal. Perbaiki sumber datanya.');
        }
        $parts = explode('.', (string) $value);
        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    private function line(string $code, string $label, string $formula, $cents): array
    {
        $rounded = (int) round($cents);
        return ['code' => $code, 'label' => $label, 'formula' => $formula, 'amount_cents' => $rounded, 'amount' => $rounded / 100];
    }

    private function invalid(string $message): void
    {
        throw ValidationException::withMessages(['calculation' => $message]);
    }
}
