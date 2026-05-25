<?php

namespace App\Services\ContractRenewals;

use App\Models\ContractTemplate;
use App\Models\EmployeeContract;
use App\Models\EmployeeContractRenewal;
use App\Models\Epayslip\KomponenGaji;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class ContractRenewalSalaryService
{
    public function resolveSalary(EmployeeContractRenewal $renewal): float
    {
        $previousSalary = $this->previousContractSalary($renewal);
        $latestBasicSalary = $this->latestBasicSalaryFromPayslip((string) $renewal->employee_nik);

        return max((float) $previousSalary, (float) $latestBasicSalary);
    }

    private function latestBasicSalaryFromPayslip(string $nik): ?float
    {
        if ($nik === '') {
            return null;
        }

        try {
            $rows = KomponenGaji::query()
                ->join('data_karyawans', 'data_karyawans.id', '=', 'komponen_gajis.data_karyawan_id')
                ->where('data_karyawans.nik', $nik)
                ->whereNotNull('komponen_gajis.gaji_pokok')
                ->orderByDesc('komponen_gajis.periode')
                ->orderByDesc('komponen_gajis.id')
                ->limit(12)
                ->get(['komponen_gajis.gaji_pokok']);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        foreach ($rows as $row) {
            $salary = $this->normalizeMoney($row->gaji_pokok);

            if ($salary !== null && $salary > 0) {
                return $salary;
            }
        }

        return null;
    }

    private function previousContractSalary(EmployeeContractRenewal $renewal): ?float
    {
        $nik = (string) $renewal->employee_nik;

        if ($nik === '') {
            return null;
        }

        $contractNumber = trim((string) $renewal->current_contract_number);

        if ($contractNumber !== '') {
            $matchedSalary = $this->contractSalaryQuery($nik)
                ->where(function (Builder $query) use ($contractNumber) {
                    $query->where('contract_number', $contractNumber)
                        ->orWhere('pkwt_number', $contractNumber)
                        ->orWhere('addendum_number', $contractNumber);
                })
                ->orderByDesc('id')
                ->value('salary');

            $matchedSalary = $this->normalizeMoney($matchedSalary);

            if ($matchedSalary !== null && $matchedSalary > 0) {
                return $matchedSalary;
            }
        }

        $currentEndDate = $renewal->current_contract_end_date
            ? Carbon::parse($renewal->current_contract_end_date)->format('Y-m-d')
            : null;

        $query = $this->contractSalaryQuery($nik)
            ->when($currentEndDate, function (Builder $query) use ($currentEndDate) {
                $query->where(function (Builder $dateQuery) use ($currentEndDate) {
                    $dateQuery->whereDate('first_extension_end_date', '<=', $currentEndDate)
                        ->orWhereDate('contract_end_date', '<=', $currentEndDate);
                });
            })
            ->orderByRaw('COALESCE(first_extension_end_date, contract_end_date, created_at) DESC')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['salary']);

        foreach ($query as $contract) {
            $salary = $this->normalizeMoney($contract->salary);

            if ($salary !== null && $salary > 0) {
                return $salary;
            }
        }

        return null;
    }

    private function contractSalaryQuery(string $nik): Builder
    {
        return EmployeeContract::query()
            ->where(function (Builder $query) use ($nik) {
                $query->where('nik', $nik)
                    ->orWhere('employee_nik', $nik);
            })
            ->whereIn('contract_type', [
                ContractTemplate::TYPE_PKWT_1,
                ContractTemplate::TYPE_ADDENDUM_PKWT,
            ])
            ->whereNotIn('status', [
                EmployeeContract::STATUS_CANCELLED,
                EmployeeContract::STATUS_REJECTED,
            ])
            ->whereNotNull('salary');
    }

    private function normalizeMoney($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9,.\-]/', '', $value);

        if ($normalized === '' || $normalized === null) {
            return null;
        }

        $commaCount = substr_count($normalized, ',');
        $dotCount = substr_count($normalized, '.');

        if ($commaCount > 0 && $dotCount > 0) {
            if (strrpos($normalized, ',') > strrpos($normalized, '.')) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif ($commaCount > 1) {
            $normalized = str_replace(',', '', $normalized);
        } elseif ($commaCount === 1) {
            $decimalLength = strlen($normalized) - (int) strrpos($normalized, ',') - 1;
            $normalized = $decimalLength <= 2
                ? str_replace(',', '.', $normalized)
                : str_replace(',', '', $normalized);
        } elseif ($dotCount > 1) {
            $normalized = str_replace('.', '', $normalized);
        } elseif ($dotCount === 1) {
            $decimalLength = strlen($normalized) - (int) strrpos($normalized, '.') - 1;

            if ($decimalLength === 3) {
                $normalized = str_replace('.', '', $normalized);
            }
        }

        return is_numeric($normalized) ? (float) $normalized : null;
    }
}
