<?php

namespace App\Services\Salary;

use App\Models\EmployeeSalaryHistory;
use App\Models\Employee;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class EmployeeSalaryService
{
    public function forRows(array $rows, bool $lock = false): array
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
            ->when($lock, fn ($query) => $query->lockForUpdate())
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

    public function payrollFixedAllowance(EmployeeSalaryHistory $salary, Employee $employee, string $period): array
    {
        if (!$employee->entry_date) {
            throw ValidationException::withMessages(['custom'=>'Lengkapi tanggal masuk sebelum merinci tunjangan tetap.']);
        }
        $end = CarbonImmutable::parse($period.'-01')->endOfMonth();
        $years = max(0, (int) $employee->entry_date->copy()->startOfDay()->diffInYears($end->startOfDay(), false));
        $tenure = 50000 * $years;
        $other = (int) round((float) $salary->fixed_allowance) - $tenure;
        if ($other < 0) {
            throw ValidationException::withMessages(['custom'=>'Tunjangan tetap dalam riwayat lebih kecil dari tunjangan masa kerja yang dihitung. Periksa Riwayat Upah sebelum melanjutkan.']);
        }
        return ['tenure_years'=>$years, 'tenure_allowance'=>$tenure, 'other_fixed_allowance'=>$other];
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
