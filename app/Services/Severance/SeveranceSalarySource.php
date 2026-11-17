<?php

namespace App\Services\Severance;

use App\Models\Employee;
use App\Models\Epayslip\Karyawan;
use App\Models\Epayslip\KomponenGaji;
use App\Models\SeveranceRequest;
use App\Support\SafeExceptionLogger;
use Illuminate\Validation\ValidationException;
use Throwable;

class SeveranceSalarySource
{
    public function resolve(SeveranceRequest $entry): array
    {
        $employee = Employee::where('nik', $entry->employee_nik)->first();
        if (!$employee || !$employee->no_ktp) { $this->invalid('Lengkapi identitas karyawan pada HRIS sebelum memeriksa upah.'); }
        try {
            $employees = Karyawan::where('nik', $entry->employee_nik)->where('no_ktp', $employee->no_ktp)->limit(2)->pluck('id');
            if ($employees->count() !== 1) { $this->invalid('Identitas V Payslip tidak ditemukan atau berulang. Periksa kecocokan NIK dan KTP di kedua sistem.'); }
            $slip = KomponenGaji::where('data_karyawan_id', $employees->first())
                ->whereRaw('UPPER(TRIM(status_gaji)) = ?', ['FULL'])
                ->whereDate('akhir_periode', '<=', $entry->termination_date->format('Y-m-d'))
                ->orderByDesc('akhir_periode')->orderByDesc('id')
                ->first(['id', 'periode', 'akhir_periode', 'gaji_pokok', 'tunj_pengawas', 'tunj_mk', 'status_gaji']);
        } catch (ValidationException $exception) { throw $exception; }
        catch (Throwable $exception) {
            app(SafeExceptionLogger::class)->warning('severance.salary_source', $exception);
            $this->invalid('V Payslip belum dapat diakses. Coba lagi setelah koneksi tersedia; pengajuan tetap tersimpan.');
        }
        if (!$slip) { $this->invalid('Belum ada slip FULL dengan akhir periode pada/sebelum tanggal akhir kerja. Slip PRORATE tidak digunakan.'); }
        $calculator = app(SeveranceCalculator::class);
        $snapshot = [
            'source' => 'V Payslip', 'slip_id' => $slip->id, 'period' => $slip->periode,
            'period_end' => substr((string) $slip->akhir_periode, 0, 10), 'status' => 'FULL',
            'basic_salary' => $calculator->cents($slip->gaji_pokok) / 100,
            'position_allowance' => $calculator->cents($slip->tunj_pengawas ?? 0) / 100,
            'tenure_allowance' => $calculator->cents($slip->tunj_mk ?? 0) / 100,
        ];
        if ($snapshot['basic_salary'] <= 0) { $this->invalid('Gaji pokok pada slip FULL harus lebih besar dari nol. Perbaiki sumber V Payslip.'); }
        $snapshot['hash'] = hash('sha256', json_encode($snapshot));
        return $snapshot;
    }

    private function invalid(string $message): void
    {
        throw ValidationException::withMessages(['salary' => $message]);
    }
}
