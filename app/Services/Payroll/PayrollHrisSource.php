<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\NationalHoliday;
use App\Models\OvertimeOrder;
use App\Models\OvertimePayRule;
use App\Models\Presensi;
use App\Models\Roster;
use App\Models\RosterSchedule;
use App\Services\Overtime\OvertimePayCalculatorService;
use App\Services\Presensi\AttendancePeriodLockService;
use App\Services\Presensi\OvertimeOrderService;
use App\Services\Salary\EmployeeSalaryService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PayrollHrisSource
{
    private const ROSTER_WEEKS = ['satu', 'dua', 'tiga', 'empat', 'lima'];
    private ?array $holidays = null;

    public function __construct(
        private EmployeeSalaryService $salary,
        private OvertimePayCalculatorService $overtime,
        private OvertimeOrderService $orders,
        private AttendancePeriodLockService $periods,
    ) {}

    public function latestClosedPeriod(): string
    {
        $today = today();
        $current = $this->periods->periodForMonth($today->format('Y-m'));

        return ($current['end_date']->lt($today) ? $today : $today->subMonthNoOverflow())->format('Y-m');
    }

    public function validatePeriod(array $settings): void
    {
        $period = $this->periods->periodForMonth($settings['period']);
        $start = $period['start_date'];
        $end = $period['end_date'];
        if ($settings['start_date'] !== $start->toDateString() || $settings['end_date'] !== $end->toDateString()) {
            $this->invalid('Cut-off periode '.$settings['period'].' harus '.$start->format('d/m/Y').' sampai '.$end->format('d/m/Y').'. Sesuaikan tanggal pada formulir.');
        }
        if ($end->gte(today())) {
            $this->invalid('Cut-off periode '.$settings['period'].' berakhir '.$end->format('d/m/Y').'. Payroll bisa dihitung mulai '.$end->copy()->addDay()->format('d/m/Y').'.');
        }
    }

    public function load(array $niks, array $settings): array
    {
        $range = [$settings['start_date'], $settings['end_date']];
        $this->holidays ??= Schema::hasTable('national_holidays')
            ? NationalHoliday::whereBetween('holiday_date', $range)->pluck('holiday_date')->all() : [];
        $month = CarbonImmutable::parse($settings['period'].'-01');
        $orders = OvertimeOrder::whereIn('nik_karyawan', $niks)->whereBetween('overtime_date', $range)
            ->whereIn('employee_response_status', [OvertimeOrder::RESPONSE_ACCEPTED, OvertimeOrder::RESPONSE_PENDING])->get();
        $dates = [];
        foreach ($niks as $nik) {
            $dates['start:'.$nik] = ['nik'=>$nik, 'berlaku_mulai'=>$month->toDateString()];
            $dates['end:'.$nik] = ['nik'=>$nik, 'berlaku_mulai'=>$month->endOfMonth()->toDateString()];
        }
        foreach ($orders as $order) {
            $dates['ot:'.$order->id] = ['nik'=>$order->nik_karyawan, 'berlaku_mulai'=>$order->overtime_date->toDateString()];
        }
        $attendance = Presensi::whereIn('nik_karyawan', $niks)->whereBetween('tanggal', $range)
            ->get(['id', 'nik_karyawan', 'tanggal', 'status_presensi', 'status_absen', 'jam_masuk', 'jam_pulang']);
        $rosters = Roster::whereIn('nik_karyawan', $niks)->activeApplication()->with('periodeKerjaRoster')
            ->whereHas('periodeKerjaRoster', fn ($q) => $q->where('tipe_rencana', 2))
            ->where(function ($query) use ($range) {
                $query->where(fn ($q) => $q->where('tgl_awal_kerja', '<=', $range[1])->where('tgl_akhir_kerja', '>=', $range[0]))
                    ->orWhereHas('periodeKerjaRoster', function ($q) use ($range) {
                        $q->where('tipe_rencana', 2)->where(function ($dates) use ($range) {
                            foreach (self::ROSTER_WEEKS as $week) {
                                $dates->orWhere(fn ($d) => $d->where($week, 'BEKERJA')->whereBetween('tanggal_'.$week, $range));
                            }
                        });
                    });
            })->get(['id', 'nik_karyawan', 'roster_schedule_id', 'tgl_awal_kerja', 'tgl_akhir_kerja', 'status_pengajuan', 'status_pengajuan_hrd']);
        $importedIncentives = RosterSchedule::whereIn('employee_nik', $niks)->active()
            ->where('source', RosterSchedule::SOURCE_IMPORT)->where('realization_type', RosterSchedule::REALIZATION_INSENTIF)
            ->where('off_start', '<=', $range[1])->where('off_end', '>=', $range[0])->get(['id', 'employee_nik']);

        return [
            'salaries'=>$this->salary->forRows($dates),
            'attendance'=>$attendance->groupBy('nik_karyawan'),
            'orders'=>$orders->groupBy('nik_karyawan'),
            'rosters'=>$rosters->groupBy('nik_karyawan'),
            'imported_incentives'=>$importedIncentives->groupBy('employee_nik'),
        ];
    }

    public function apply(array &$columns, Employee $employee, array $settings, array $data, array &$minutes, ?int $manualOvertime = null): array
    {
        $nik = $employee->nik;
        $month = CarbonImmutable::parse($settings['period'].'-01');
        $monthEnd = $month->endOfMonth();
        if (!$employee->entry_date || $employee->entry_date->toDateString() > $month->toDateString()
            || ($employee->tgl_resign && $employee->tgl_resign->toDateString() < $monthEnd->toDateString())) {
            $this->invalid('Upah HRIS otomatis memerlukan bulan kerja penuh dan tanggal masuk yang valid. Gunakan sumber Excel untuk prorata.');
        }
        $salary = $data['salaries']['start:'.$nik] ?? null;
        if (!$salary || $salary->id !== ($data['salaries']['end:'.$nik]->id ?? null) || $salary->basic_salary <= 0) {
            $this->invalid('Riwayat upah belum lengkap atau berubah di tengah bulan. Periksa Riwayat Gaji atau hitung prorata dengan sumber Excel.');
        }
        if ($manualOvertime === null && $employee->isSmelter()
            && $this->overtime->resolveScheduleTypeFromWorkPattern($employee->workPattern) === OvertimePayRule::SCHEDULE_FIVE_TWO) {
            foreach ($this->holidays as $holiday) {
                if ($holiday->toDateString() >= $employee->entry_date->toDateString()) {
                    $this->invalid('Lembur libur Smelter 5:2 memiliki ketentuan minimum perusahaan. Gunakan nominal terverifikasi dari sumber Excel.');
                }
            }
        }
        foreach (['H', 'J', 'K', 'N', 'P', 'X'] as $key) {
            if (filled($columns[$key] ?? null)) {
                $this->invalid('Kosongkan kolom '.$key.' pada FORMAT BULK. Kolom ini dihitung dari HRIS.');
            }
        }

        $parts = $this->salary->payrollFixedAllowance($salary, $employee, $settings['period']);
        $years = $parts['tenure_years'];
        $tenure = $parts['tenure_allowance'];
        $basic = (int) round((float) $salary->basic_salary);
        $fixed = $parts['other_fixed_allowance'];
        $attendance = $data['attendance']->get($nik, collect())->groupBy(fn (Presensi $row) => $row->tanggal->toDateString());
        $present = [];
        foreach ($attendance as $date => $records) {
            foreach ($records as $record) {
                if (filled($record->status_presensi) || (!$record->jam_masuk && !$record->jam_pulang)) { continue; }
                if ($record->status_absen === Presensi::STATUS_ABSEN_PENDING_REVIEW) {
                    $this->invalid('Presensi '.$date.' masih menunggu review. Selesaikan review sebelum menghitung uang makan.');
                }
                if ($record->status_absen !== Presensi::STATUS_ABSEN_REJECTED && $record->jam_masuk && $record->jam_pulang
                    && $record->jam_pulang->greaterThan($record->jam_masuk)) {
                    $present[$date] = $record->id;
                }
            }
        }

        $incentive = $this->incentiveDates($data, $nik, $settings);
        $overtime = $manualOvertime === null ? $this->overtimeLines($data, $employee, $attendance, $minutes) : [];
        $columns['H'] = count($present);
        $columns['J'] = $basic + $fixed;
        $columns['K'] = 17500 * count($present);
        $columns['N'] = $tenure;
        $columns['P'] = $manualOvertime ?? array_sum(array_column($overtime, 'amount'));
        $columns['X'] = (int) round((float) $salary->basic_salary * count($incentive) / 30);

        return ['salary_history_id'=>$salary->id, 'basic_salary'=>$basic, 'other_fixed_allowance'=>$fixed,
            'tenure_years'=>$years, 'meal_attendance'=>$present, 'roster_incentive'=>$incentive, 'overtime'=>$overtime];
    }

    private function incentiveDates(array $data, string $nik, array $settings): array
    {
        $dates = [];
        $schedules = [];
        foreach ($data['rosters']->get($nik, []) as $roster) {
            if ((int) $roster->status_pengajuan !== 1 || (int) $roster->status_pengajuan_hrd !== 1) {
                $this->invalid('Pengajuan insentif roster #'.$roster->id.' belum disetujui HOD dan HR.');
            }
            $schedules[] = $roster->roster_schedule_id;
            $start = max($settings['start_date'], (string) $roster->tgl_awal_kerja);
            $end = min($settings['end_date'], (string) $roster->tgl_akhir_kerja);
            if ($roster->tgl_awal_kerja && $roster->tgl_akhir_kerja) {
                for ($day = CarbonImmutable::parse($start); $day->toDateString() <= $end; $day = $day->addDay()) {
                    $dates[$day->toDateString()] = $roster->id;
                }
            }
            foreach (self::ROSTER_WEEKS as $week) {
                $plan = $roster->periodeKerjaRoster;
                $date = (string) $plan->{'tanggal_'.$week};
                if (strtoupper((string) $plan->{$week}) !== 'BEKERJA') { continue; }
                if (!$date) { $this->invalid('Tanggal BEKERJA pada pengajuan roster #'.$roster->id.' belum lengkap.'); }
                if ($date >= $settings['start_date'] && $date <= $settings['end_date']) { $dates[$date] = $roster->id; }
            }
        }
        foreach ($data['imported_incentives']->get($nik, []) as $schedule) {
            if (!in_array($schedule->id, $schedules)) {
                $this->invalid('Jadwal roster #'.$schedule->id.' ditandai insentif tetapi tidak memiliki pengajuan yang menetapkan hari insentif.');
            }
        }
        ksort($dates);
        return $dates;
    }

    private function overtimeLines(array $data, Employee $employee, $attendance, array &$minutes): array
    {
        $lines = [];
        $paidDates = [];
        foreach ($data['orders']->get($employee->nik, []) as $order) {
            $id = $order->id;
            if ($order->employee_response_status !== OvertimeOrder::RESPONSE_ACCEPTED) {
                $this->invalid('Perintah lembur #'.$id.' masih menunggu respons.');
            }
            if (!array_key_exists($id, $minutes)) { $this->invalid('Isi menit yang disahkan untuk perintah lembur #'.$id.' di sheet LEMBUR, termasuk bila 0.'); }
            $paid = $minutes[$id];
            unset($minutes[$id]);
            $date = $order->overtime_date->toDateString();
            $line = ['order_id'=>$id, 'date'=>$date, 'minutes'=>$paid, 'amount'=>0];
            if ($paid > 0) {
                $records = $attendance->get($date, collect())->filter(fn (Presensi $p) =>
                    blank($p->status_presensi) && !in_array($p->status_absen, [Presensi::STATUS_ABSEN_REJECTED, Presensi::STATUS_ABSEN_PENDING_REVIEW], true));
                $record = $records->count() === 1 ? $records->first() : null;
                if (!$record || $paid > (int) $order->required_minutes || $paid > $this->orders->attendanceOverlapMinutes($order, $record)) {
                    $this->invalid('Menit lembur #'.$id.' melebihi rentang perintah/presensi atau bukti hadir belum valid.');
                }
                $salary = $data['salaries']['ot:'.$id] ?? null;
                $pattern = $employee->workPattern;
                if (!$salary || $salary->basic_salary <= 0 || !$pattern || !$pattern->isWeeklyPattern()
                    || !in_array(count($pattern->normalizeWeeklyWorkDays()), [5, 6], true)
                    || ($employee->work_pattern_start_date && $employee->work_pattern_start_date->toDateString() > $date)) {
                    $this->invalid('Perintah lembur #'.$id.' memerlukan riwayat upah dan pola mingguan 5:2 atau 6:1 yang berlaku pada tanggal lembur.');
                }
                if (isset($paidDates[$date])) {
                    $this->invalid('Ada lebih dari satu perintah lembur dibayar pada '.$date.'. Verifikasi gabungan tarif hari itu dengan sumber Excel.');
                }
                $paidDates[$date] = true;
                $schedule = $this->overtime->resolveScheduleTypeFromWorkPattern($pattern);
                $day = $this->overtime->resolveDayTypeFromOvertimeType($order->overtime_type, $schedule, $this->overtime->isShortestWorkday($pattern, $date));
                $basis = $this->salary->overtimeBasis($salary);
                $result = $this->overtime->calculate($schedule, $day, $paid, $basis);
                if ($result['overflow_minutes']) { $this->invalid('Menit lembur #'.$id.' melampaui rentang kalkulator. Verifikasi dengan sumber Excel.'); }
                $line += ['salary_history_id'=>$salary->id, 'attendance_id'=>$record->id, 'basis'=>$basis, 'day_type'=>$day,
                    'schedule_type'=>$schedule, 'warnings'=>$result['warnings']];
                $line['amount'] = (int) $result['amount'];
            }
            $lines[] = $line;
        }
        return $lines;
    }

    private function invalid(string $message): void
    {
        throw ValidationException::withMessages(['payroll'=>$message]);
    }
}
