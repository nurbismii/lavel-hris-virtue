<?php

namespace App\Services\Roster;

use App\Models\Employee;
use App\Models\RosterSchedule;
use App\Support\Roster\RosterWorkbookData;
use Illuminate\Support\Collection;

final class RosterScheduleImportValidationService
{
    public function validate(RosterWorkbookData $data): array
    {
        $periodRows = [];
        foreach ($data->rows as $row) {
            foreach ($row['periods'] as $period) {
                $periodRows[] = [$row, $period];
            }
        }

        if ($periodRows === []) {
            return $this->result([], [], []);
        }

        $nikCounts = $data->rows->pluck('nik')->map(fn ($nik) => (string) $nik)->filter()->countBy();
        $niks = $nikCounts->keys()->filter(fn ($nik) => preg_match('/^\d+$/', (string) $nik) === 1)->values();
        $employees = $niks->isEmpty()
            ? collect()
            : Employee::query()->whereIn('nik', $niks)->get(['nik', 'no_ktp', 'nama_karyawan', 'status_resign'])
                ->keyBy(fn (Employee $employee) => (string) $employee->nik);
        $schedules = $this->schedulesFor($periodRows);
        $errors = [];
        $warnings = [];
        $rows = [];
        $seenPairs = [];

        foreach ($periodRows as [$row, $period]) {
            $item = [
                'row_number' => $row['row_number'], 'nik' => (string) $row['nik'], 'no_ktp' => (string) $row['no_ktp'],
                'employee_name' => $row['employee_name'], 'hris_name' => null, 'year' => $period['year'],
                'period_number' => $period['period_number'], 'source_column' => $period['source_column'],
                'off_start' => $period['off_start'], 'action' => 'create', 'errors' => [], 'warnings' => [],
            ];
            $add = function (string $code, string $reason, bool $warning = false) use (&$item, &$errors, &$warnings): void {
                $message = ['code' => $code, 'row' => $item['row_number'], 'column' => $item['source_column'], 'reason' => $reason];
                $item[$warning ? 'warnings' : 'errors'][] = $message;
                if ($warning) { $warnings[] = $message; } else { $errors[] = $message; }
            };
            $nik = $item['nik'];
            $ktp = $item['no_ktp'];
            if ($nik === '') { $add('missing_nik', 'NIK wajib diisi'); }
            elseif (preg_match('/^\d+$/', $nik) !== 1) { $add('invalid_nik', 'NIK harus angka'); }
            elseif (($nikCounts[$nik] ?? 0) > 1) { $add('duplicate_nik', 'NIK duplikat dalam workbook'); }
            if (preg_match('/^\d{16}$/', $ktp) !== 1 || ($row['identity_error'] ?? null) === 'unsafe_numeric_identity') {
                $add('invalid_ktp', 'Nomor KTP harus 16 digit teks');
            }
            $employee = $employees->get($nik);
            if ($nik !== '' && !$employee) { $add('employee_not_found', 'Karyawan tidak ditemukan'); }
            if ($employee) {
                $item['hris_name'] = $employee->nama_karyawan;
                $storedKtp = (string) $employee->no_ktp;
                if (preg_match('/^\d{16}$/', $storedKtp) !== 1) { $add('invalid_ktp', 'KTP HRIS tidak valid'); }
                elseif (preg_match('/^\d{16}$/', $ktp) === 1 && !hash_equals($storedKtp, $ktp)) { $add('ktp_mismatch', 'Nomor KTP tidak sesuai'); }
                if ($row['employee_name'] !== '' && $row['employee_name'] !== $employee->nama_karyawan) { $add('name_mismatch', 'Nama berbeda', true); }
                if (strtoupper((string) $employee->status_resign) !== 'AKTIF') { $add('inactive_employee', 'Karyawan tidak aktif', true); }
            }
            if (!empty($period['cell_error']) || empty($period['off_start'])) {
                $add('invalid_date', 'Tanggal roster tidak valid');
            } else {
                $pair = $nik . '|' . $period['off_start'];
                if (isset($seenPairs[$pair])) { $add('duplicate_off_start', 'Tanggal off duplikat'); }
                $seenPairs[$pair] = true;
                $existing = $schedules->get($pair);
                if ($existing && $existing->source === 'manual') { $add('manual_conflict', 'Jadwal manual tidak boleh ditimpa'); }
                elseif ($existing) {
                    $item['action'] = (int) $existing->period_year === (int) $period['year'] && (int) $existing->period_number === (int) $period['period_number'] ? 'unchanged' : 'update';
                }
            }
            if (!empty($item['errors'])) { $item['action'] = 'blocked'; }
            if (!empty($period['raw_remark']) && stripos((string) $period['raw_remark'], 'review') !== false) { $add('remark_need_review', 'Remark perlu review', true); }
            $rows[] = $item;
        }

        return $this->result($rows, $errors, $warnings);
    }

    private function schedulesFor(array $periodRows): Collection
    {
        $groups = collect($periodRows)->filter(function (array $pair): bool {
            return !empty($pair[1]['off_start']) && preg_match('/^\d+$/', (string) $pair[0]['nik']) === 1;
        })->groupBy(fn (array $pair) => $pair[1]['off_start']);
        if ($groups->isEmpty()) { return collect(); }

        return RosterSchedule::query()->where(function ($query) use ($groups): void {
            foreach ($groups as $date => $group) {
                $query->orWhere(function ($dateQuery) use ($date, $group): void {
                    $dateQuery->where('off_start', $date)->whereIn('employee_nik', collect($group)->map(fn (array $pair) => (string) $pair[0]['nik'])->unique()->all());
                });
            }
        })->get(['employee_nik', 'off_start', 'source', 'period_year', 'period_number'])
            ->keyBy(fn (RosterSchedule $schedule) => $schedule->employee_nik . '|' . $schedule->getRawOriginal('off_start'));
    }

    private function result(array $rows, array $errors, array $warnings): array
    {
        return ['is_valid' => empty($errors), 'summary' => ['total_rows' => count($rows), 'blocker_count' => count($errors), 'warning_count' => count($warnings)], 'rows' => $rows, 'errors' => $errors, 'warnings' => $warnings];
    }
}
