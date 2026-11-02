<?php

namespace App\Services\Salary;

use App\Models\EmployeeSalaryHistory;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class EmployeeSalaryService
{
    public function forRows(array $rows): array
    {
        if (!$rows) {
            return [];
        }
        $result = [];
        $pending = [];
        foreach ($rows as $key => $row) {
            $pending[$row['nik']][$key] = $row['berlaku_mulai'];
        }
        $salaries = EmployeeSalaryHistory::query()->whereIn('employee_nik', array_keys($pending))
            ->where('effective_from', '<=', max(array_column($rows, 'berlaku_mulai')))
            ->orderByDesc('effective_from')->orderByDesc('id')->cursor();
        foreach ($salaries as $salary) {
            foreach ($pending[$salary->employee_nik] ?? [] as $key => $date) {
                if ($salary->effective_from->toDateString() <= $date) {
                    $result[$key] = $salary;
                    unset($pending[$salary->employee_nik][$key]);
                }
            }
        }

        return $result;
    }

    public function forDate(string $nik, $date): ?EmployeeSalaryHistory
    {
        return EmployeeSalaryHistory::query()
            ->where('employee_nik', $nik)
            ->where('effective_from', '<=', Carbon::parse($date)->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    public function requireForDate(string $nik, $date): EmployeeSalaryHistory
    {
        $salary = $this->forDate($nik, $date);
        if (!$salary) {
            throw ValidationException::withMessages([
                'overtime_date' => 'Belum ada riwayat upah yang disetujui untuk tanggal tersebut. Unggah dan konfirmasi upah awal/perubahannya terlebih dahulu.',
            ]);
        }

        return $salary;
    }

    public function overtimeBasis(EmployeeSalaryHistory $salary): float
    {
        return $this->basis((float) $salary->basic_salary, (float) $salary->fixed_allowance, (float) $salary->non_fixed_allowance);
    }

    public function basis(float $basic, float $fixed, float $nonFixed): float
    {
        if (min($basic, $fixed, $nonFixed) < 0 || !is_finite($basic + $fixed + $nonFixed)) {
            throw new \InvalidArgumentException('Komponen upah tidak valid.');
        }

        // PP 35/2021 Pasal 32(3)-(4): fixed wages or 75% of total wages, whichever is higher.
        return round(max($basic + $fixed, 0.75 * ($basic + $fixed + $nonFixed)), 2);
    }
}
