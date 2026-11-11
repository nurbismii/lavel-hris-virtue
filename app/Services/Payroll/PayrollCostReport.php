<?php

namespace App\Services\Payroll;

use App\Models\ImportHistory;
use App\Models\PayrollEntry;

class PayrollCostReport
{
    public const COMPONENTS = [
        'input.cash_income'=>'Penghasilan kas',
        'result.tax_allowance'=>'Tunjangan pajak',
        'result.employer_bpjs'=>'BPJS perusahaan',
        'result.employer_cost'=>'Total beban gaji',
    ];

    public function forPeriod(?int $employer, ?string $period, ?string $nik): array
    {
        $scope = PayrollEntry::query()
            ->join('import_histories', 'import_histories.id', '=', 'payroll_entries.import_history_id')
            ->where('import_histories.import_type', ImportHistory::TYPE_PAYROLL)
            ->where('import_histories.status', ImportHistory::STATUS_FINALIZED)
            ->when($employer !== null, fn ($query) => $query->where('payroll_entries.employer_id', $employer))
            ->when($nik !== null, fn ($query) => $query->where('payroll_entries.employee_nik', $nik));

        $period ??= (clone $scope)->max('payroll_entries.period') ?? now()->format('Y-m');
        $scope->where('payroll_entries.period', $period);

        $sums = ['COUNT(*) AS employee_count'];
        foreach (array_keys(self::COMPONENTS) as $index=>$path) {
            $sums[] = "SUM(CAST(JSON_EXTRACT(payroll_entries.snapshot, '$.$path') AS DECIMAL(20,0))) AS cost_$index";
        }
        $summary = (clone $scope)->toBase()->selectRaw(implode(', ', $sums))->first();
        $totals = [];
        foreach (array_keys(self::COMPONENTS) as $index=>$path) {
            $totals[$path] = (int) $summary->{'cost_'.$index};
        }

        return [
            'period'=>$period,
            'totals'=>$totals,
            'count'=>(int) $summary->employee_count,
            'entries'=>$scope->orderBy('payroll_entries.id')->simplePaginate(50, ['payroll_entries.*'])->withQueryString(),
        ];
    }
}
