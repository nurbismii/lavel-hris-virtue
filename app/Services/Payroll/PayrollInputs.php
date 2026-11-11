<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\OvertimeOrder;
use App\Models\PayrollEntry;
use App\Models\PayrollInput;
use App\Models\Perusahaan;
use App\Services\Presensi\AttendancePeriodLockService;
use App\Services\Salary\EmployeeSalaryService;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollInputs
{
    public const PAY_FIELDS = [
        'L'=>'Tunjangan pengawas', 'M'=>'Transport dan pulsa', 'O'=>'Tunjangan jabatan C', 'Q'=>'Upah jam mesin',
        'U'=>'Tunjangan fungsional', 'V'=>'Tunjangan lapangan', 'W'=>'Bonus', 'Y'=>'Rapel upah',
        'AA'=>'Potongan unpaid leave', 'AB'=>'Potongan alpa', 'AC'=>'Potongan lain', 'AI'=>'THR',
    ];
    public const RECURRING_KEYS = ['L', 'M', 'U', 'V', 'AC'];
    public const BPJS_AMOUNTS = ['upah_bpjs_tk'=>'Upah BPJS Ketenagakerjaan', 'upah_bpjs_kes'=>'Upah BPJS Kesehatan', 'upah_minimum'=>'UMK/UMP'];
    public const BPJS_PROGRAMS = ['jht_aktif'=>'JHT', 'jp_aktif'=>'JP', 'kes_aktif'=>'BPJS Kesehatan', 'jkk_jkm_aktif'=>'JKK dan JKM'];
    public const MONTHLY_TAX = ['natura'=>'Natura kena pajak', 'iuran_pensiun_lain'=>'Iuran pensiun lain', 'zakat'=>'Zakat melalui pemberi kerja'];
    public const YTD_FIELDS = ['ytd_bruto'=>'Bruto', 'ytd_iuran'=>'Iuran pegawai', 'ytd_zakat'=>'Zakat', 'ytd_pph21'=>'PPh 21 dipotong'];

    public function __construct(private EmployeeSalaryService $salary, private AttendancePeriodLockService $periods) {}

    public static function periodRules(): array
    {
        return [
            'employer_id'=>['required','integer','exists:perusahaan,id'],
            'period'=>['required','date_format:Y-m','after_or_equal:'.config('payroll.valid_from'),'before_or_equal:'.config('payroll.valid_through')],
        ];
    }

    public function cutoff(string $period): array
    {
        $dates = $this->periods->periodForMonth($period);
        return ['start_date'=>$dates['start_date']->toDateString(), 'end_date'=>$dates['end_date']->toDateString()];
    }

    public function employees(int $employer, string $period): Builder
    {
        $start = CarbonImmutable::parse($period.'-01');
        return Employee::whereHas('departemen', fn ($q) => $q->where('perusahaan_id', $employer))
            ->where(fn ($q) => $q->whereNull('entry_date')->orWhere('entry_date', '<=', $start->endOfMonth()))
            ->where(fn ($q) => $q->whereNull('tgl_resign')->orWhere('tgl_resign', '>=', $start))
            ->whereNotExists(PayrollEntry::query()->selectRaw('1')->where('employer_id', $employer)
                ->where('period', $period)->whereColumn('employee_nik', 'employees.nik')->toBase());
    }

    public function version(int $employer, string $period): int
    {
        return (int) $this->inputs($employer, $period)->max('id');
    }

    public function records(int $employer, string $period, iterable $niks, ?int $version = null): Collection
    {
        $latest = $this->inputs($employer, $period)->whereIn('employee_nik', $niks)
            ->when($version !== null, fn ($q) => $q->where('id', '<=', $version))
            ->selectRaw('MAX(id)')->groupBy('employee_nik', 'period');
        return PayrollInput::whereIn('id', $latest)->orderByDesc('period')->get()->groupBy('employee_nik');
    }

    private function inputs(int $employer, string $period): Builder
    {
        return PayrollInput::where('employer_id', $employer)->whereBetween('period', [substr($period, 0, 4).'-01', $period]);
    }

    public function forEmployee(Collection $records, string $period): array
    {
        $current = $records->firstWhere('period', $period);
        $profile = $records->first(fn (PayrollInput $input) => !empty($input->data['profile']));
        $recurring = $records->first(fn (PayrollInput $input) => array_key_exists('recurring', $input->data));
        $custom = $records->first(fn (PayrollInput $input) => array_key_exists('custom_recurring', $input->data));
        return ['current'=>$current, 'profile'=>$profile?->data['profile'] ?? [], 'recurring'=>$recurring?->data['recurring'] ?? [],
            'custom_recurring'=>$custom?->data['custom_recurring'] ?? []];
    }

    public function orders(string $nik, string $period): Builder
    {
        $cutoff = $this->cutoff($period);
        return OvertimeOrder::where('nik_karyawan', $nik)->accepted()->inDateRange($cutoff['start_date'], $cutoff['end_date']);
    }

    public function save(int $employer, string $period, string $nik, array $values, string $actor): void
    {
        DB::transaction(function () use ($employer, $period, $nik, $values, $actor) {
            Perusahaan::whereKey($employer)->lockForUpdate()->firstOrFail();
            $employee = $this->employees($employer, $period)->whereKey($nik)->firstOrFail();
            $record = $this->forEmployee($this->records($employer, $period, [$nik])->get($nik, collect()), $period);
            if ((int) $values['revision'] !== (int) $record['current']?->id) {
                $this->invalid('Isian payroll telah diubah. Muat ulang halaman sebelum menyimpan.');
            }
            $profile = $record['profile'];
            if (!empty($values['profile'])) {
                $salary = $this->salary->forDate($nik, $period.'-01');
                if (!$salary) { $this->invalid('Riwayat Gaji yang berlaku pada awal bulan belum tersedia.'); }
                $profile = $values['profile'] + ['salary_history_id'=>$salary->id, 'subjek_pajak'=>'DN_SETAHUN', 'fasilitas'=>'NORMAL'];
            }

            $minutes = array_filter($values['minutes'] ?? [], fn ($minute) => $minute !== null && $minute !== '');
            $orderIds = $minutes ? $this->orders($nik, $period)->whereIn('id', array_keys($minutes))->pluck('id')->all() : [];
            if (count($minutes) !== count($orderIds)) { $this->invalid('Daftar perintah lembur berubah. Muat ulang halaman.'); }
            if (isset($values['manual_overtime']) && $minutes) { $this->invalid('Kosongkan menit lembur saat memakai nominal lembur khusus.'); }

            $data = [
                'profile'=>$profile,
                'components'=>array_map('intval', array_filter($values['components'] ?? [], fn ($value) => $value !== null && $value !== '')),
                'tax'=>array_map('intval', array_filter($values['tax'] ?? [], fn ($value) => $value !== null && $value !== '')),
                'minutes'=>array_map('intval', $minutes),
                'manual_overtime'=>isset($values['manual_overtime']) ? (int) $values['manual_overtime'] : null,
                'overtime_note'=>$values['overtime_note'] ?? null,
                'separate_reason'=>$values['separate_reason'] ?? null,
            ];
            $components = app(SalaryComponentService::class);
            $data['custom'] = $components->snapshots($values['custom'] ?? [], false);
            if (array_key_exists('custom_recurring', $values)) {
                $data['custom_recurring'] = $components->snapshots($values['custom_recurring'], true);
            } elseif (isset($record['current']?->data['custom_recurring'])) {
                $data['custom_recurring'] = $record['current']->data['custom_recurring'];
            }
            if (array_key_exists('recurring', $values)) {
                $data['recurring'] = array_filter(array_map('intval', $values['recurring'] ?? []));
            } elseif (isset($record['current']?->data['recurring'])) {
                $data['recurring'] = $record['current']->data['recurring'];
            }
            $effective = $data['custom'] + ($data['custom_recurring'] ?? $record['custom_recurring']);
            $active = \App\Models\SalaryComponent::whereIn('id', array_keys($effective))->pluck('id')->all();
            $effective = array_intersect_key($effective, array_flip($active));
            if ($components->fixedIncomeTotal($effective) > 0) {
                $salary = $this->salary->forDate($nik, $period.'-01');
                if (!$salary) { $this->invalid('Riwayat Upah harus tersedia sebelum merinci tunjangan tetap.'); }
                $parts = $this->salary->payrollFixedAllowance($salary, $employee, $period);
                $components->assertFixedBreakdown($effective, $parts['other_fixed_allowance']);
            }
            if ($record['current'] && Arr::sortRecursive($record['current']->data) === Arr::sortRecursive($data)) { return; }
            PayrollInput::create(['employer_id'=>$employer, 'employee_nik'=>$nik, 'period'=>$period, 'data'=>$data, 'created_by'=>$actor]);
        }, 3);
    }

    public function rows(array $settings, int &$separate): Generator
    {
        $employer = $settings['employer_id'];
        $period = $settings['period'];
        $activeComponents = \App\Models\SalaryComponent::pluck('id')->all();
        $number = 0;
        foreach ($this->employees($employer, $period)->select('nik')->lazyById(200, 'nik')->chunk(200) as $employees) {
            $records = $this->records($employer, $period, $employees->pluck('nik')->all(), $settings['input_version']);
            $rows = [];
            foreach ($employees as $employee) {
                $record = $this->forEmployee($records->get($employee->nik, collect()), $period);
                $monthly = $record['current']?->data ?? [];
                if (!empty($monthly['separate_reason'])) { $separate++; continue; }
                $profile = $record['profile'];
                $components = $monthly['components'] ?? [];
                $recurring = array_diff_key($record['recurring'], $components);
                $custom = array_intersect_key(($monthly['custom'] ?? []) + $record['custom_recurring'], array_flip($activeComponents));
                $rows[++$number] = [
                    'columns'=>['A'=>$employee->nik] + $components + $recurring,
                    'parameters'=>$profile ? Arr::except($profile, 'salary_history_id') + ($monthly['tax'] ?? [])
                        + ['natura'=>0, 'iuran_pensiun_lain'=>0, 'zakat'=>0, 'masa_terakhir'=>0] : [],
                    'duplicate'=>false,
                    'profile_salary_id'=>$profile['salary_history_id'] ?? null,
                    'minutes'=>$monthly['minutes'] ?? [],
                    'manual_overtime'=>$monthly['manual_overtime'] ?? null,
                    'overtime_note'=>$monthly['overtime_note'] ?? null,
                    'recurring_components'=>$recurring,
                    'custom_components'=>$custom,
                ];
            }
            if ($rows) { yield $rows; }
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['payroll'=>$message]);
    }
}
