<?php

namespace App\Services\Presensi;

use App\Models\Employee;
use App\Models\Roster;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TenTwoRosterPlanService
{
    public const TYPE_CUTI_ROSTER = 1;
    public const TYPE_INSENTIF = 2;

    private const WORK_WEEKS = 10;
    private const OFF_WEEKS = 2;
    private const CYCLE_DAYS = 84;
    private const APPROVED = 1;
    private const REJECTED = 2;

    private $approvedCutiRosterDateCache = [];

    public function isTenTwoPattern($pattern): bool
    {
        if (!$pattern || ($pattern->pattern_basis ?: 'cycle') !== 'cycle') {
            return false;
        }

        return (int) $pattern->work_duration_value === self::WORK_WEEKS
            && (string) $pattern->work_duration_unit === 'week'
            && (int) $pattern->off_duration_value === self::OFF_WEEKS
            && (string) $pattern->off_duration_unit === 'week';
    }

    public function preloadApprovedCutiRosterDates(Collection $employees, $startDate, $endDate): void
    {
        if ($employees->isEmpty()) {
            return;
        }

        if (method_exists($employees, 'loadMissing')) {
            $employees->loadMissing('workPattern');
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        $tenTwoEmployees = $employees
            ->filter(fn(Employee $employee) => filled($employee->nik) && $this->isTenTwoPattern($employee->workPattern))
            ->values();

        if ($tenTwoEmployees->isEmpty()) {
            return;
        }

        foreach ($tenTwoEmployees as $employee) {
            foreach (CarbonPeriod::create($start, $end) as $date) {
                $this->approvedCutiRosterDateCache[$employee->nik][$date->toDateString()] = false;
            }
        }

        $rows = $this->approvedCutiRosterQuery($tenTwoEmployees->pluck('nik')->all())
            ->whereDate('cuti_roster.tgl_mulai_cuti', '<=', $end->toDateString())
            ->whereDate('cuti_roster.tgl_mulai_cuti_berakhir', '>=', $start->toDateString())
            ->get([
                'cuti_roster.nik_karyawan',
                'cuti_roster.tgl_mulai_cuti',
                'cuti_roster.tgl_mulai_cuti_berakhir',
            ]);

        foreach ($rows as $row) {
            $rangeStart = Carbon::parse($row->tgl_mulai_cuti)->startOfDay()->max($start);
            $rangeEnd = Carbon::parse($row->tgl_mulai_cuti_berakhir)->startOfDay()->min($end);

            foreach (CarbonPeriod::create($rangeStart, $rangeEnd) as $date) {
                $this->approvedCutiRosterDateCache[$row->nik_karyawan][$date->toDateString()] = true;
            }
        }
    }

    public function hasApprovedCutiRosterDate(Employee $employee, $date): bool
    {
        if (!$this->isTenTwoPattern($employee->workPattern)) {
            return false;
        }

        $dateString = Carbon::parse($date)->toDateString();

        if (array_key_exists($dateString, $this->approvedCutiRosterDateCache[$employee->nik] ?? [])) {
            return (bool) $this->approvedCutiRosterDateCache[$employee->nik][$dateString];
        }

        $exists = $this->approvedCutiRosterQuery([$employee->nik])
            ->whereDate('cuti_roster.tgl_mulai_cuti', '<=', $dateString)
            ->whereDate('cuti_roster.tgl_mulai_cuti_berakhir', '>=', $dateString)
            ->exists();

        $this->approvedCutiRosterDateCache[$employee->nik][$dateString] = $exists;

        return $exists;
    }

    public function isDateInTenTwoOffSegment(Employee $employee, $date): bool
    {
        $employee->loadMissing('workPattern');

        if (!$this->isTenTwoPattern($employee->workPattern) || blank($employee->work_pattern_start_date)) {
            return false;
        }

        $targetDate = Carbon::parse($date)->startOfDay();
        $patternStart = Carbon::parse($employee->work_pattern_start_date)->startOfDay();

        if ($targetDate->lt($patternStart)) {
            return false;
        }

        $daysSinceStart = $patternStart->diffInDays($targetDate);
        $dayInCycle = $daysSinceStart % self::CYCLE_DAYS;

        return $dayInCycle >= (self::WORK_WEEKS * 7);
    }

    public function reminderCycleFor(Employee $employee, int $daysBeforeWorkEnd, ?Carbon $today = null): ?array
    {
        $employee->loadMissing('workPattern');

        if (!$this->isTenTwoPattern($employee->workPattern) || blank($employee->work_pattern_start_date)) {
            return null;
        }

        $today = $today ? $today->copy()->startOfDay() : Carbon::today();
        $targetWorkEnd = $today->copy()->addDays($daysBeforeWorkEnd);
        $patternStart = Carbon::parse($employee->work_pattern_start_date)->startOfDay();
        $firstWorkEnd = $patternStart->copy()->addWeeks(self::WORK_WEEKS)->subDay();
        $daysSinceFirstWorkEnd = $firstWorkEnd->diffInDays($targetWorkEnd, false);

        if ($daysSinceFirstWorkEnd < 0 || $daysSinceFirstWorkEnd % self::CYCLE_DAYS !== 0) {
            return null;
        }

        $workStart = $targetWorkEnd->copy()->subWeeks(self::WORK_WEEKS)->addDay();
        $offStart = $targetWorkEnd->copy()->addDay();
        $offEnd = $offStart->copy()->addWeeks(self::OFF_WEEKS)->subDay();

        return [
            'work_start' => $workStart,
            'work_end' => $targetWorkEnd,
            'off_start' => $offStart,
            'off_end' => $offEnd,
        ];
    }

    public function hasActiveRosterPlanForCycle(string $nikKaryawan, Carbon $offStart, Carbon $offEnd): bool
    {
        $start = $offStart->toDateString();
        $end = $offEnd->toDateString();

        return Roster::query()
            ->join('periode_kerja_roster', 'periode_kerja_roster.cuti_roster_id', '=', 'cuti_roster.id')
            ->where('cuti_roster.nik_karyawan', $nikKaryawan)
            ->where('cuti_roster.status_pengajuan', '!=', self::REJECTED)
            ->where('cuti_roster.status_pengajuan_hrd', '!=', self::REJECTED)
            ->where(function (Builder $query) {
                $query->whereNull('cuti_roster.delegate_status')
                    ->orWhere('cuti_roster.delegate_status', '!=', self::REJECTED);
            })
            ->whereIn('periode_kerja_roster.tipe_rencana', [self::TYPE_CUTI_ROSTER, self::TYPE_INSENTIF])
            ->where(function (Builder $query) use ($start, $end) {
                $query
                    ->where(function (Builder $range) use ($start, $end) {
                        $range->whereNotNull('cuti_roster.tgl_mulai_cuti')
                            ->whereNotNull('cuti_roster.tgl_mulai_cuti_berakhir')
                            ->whereDate('cuti_roster.tgl_mulai_cuti', '<=', $end)
                            ->whereDate('cuti_roster.tgl_mulai_cuti_berakhir', '>=', $start);
                    })
                    ->orWhere(function (Builder $range) use ($start, $end) {
                        $range->whereNotNull('cuti_roster.tgl_mulai_off')
                            ->whereNotNull('cuti_roster.tgl_mulai_off_berakhir')
                            ->whereDate('cuti_roster.tgl_mulai_off', '<=', $end)
                            ->whereDate('cuti_roster.tgl_mulai_off_berakhir', '>=', $start);
                    })
                    ->orWhere(function (Builder $range) use ($start, $end) {
                        $range->whereNotNull('cuti_roster.tgl_awal_kerja')
                            ->whereNotNull('cuti_roster.tgl_akhir_kerja')
                            ->whereDate('cuti_roster.tgl_awal_kerja', '<=', $end)
                            ->whereDate('cuti_roster.tgl_akhir_kerja', '>=', $start);
                    })
                    ->orWhere(function (Builder $range) use ($start, $end) {
                        $range->whereNotNull('periode_kerja_roster.periode_awal')
                            ->whereNotNull('periode_kerja_roster.periode_akhir')
                            ->whereDate('periode_kerja_roster.periode_awal', '<=', $end)
                            ->whereDate('periode_kerja_roster.periode_akhir', '>=', $start);
                    });
            })
            ->exists();
    }

    private function approvedCutiRosterQuery(array $nikKaryawan): Builder
    {
        return Roster::query()
            ->join('periode_kerja_roster', 'periode_kerja_roster.cuti_roster_id', '=', 'cuti_roster.id')
            ->whereIn('cuti_roster.nik_karyawan', $nikKaryawan)
            ->where('cuti_roster.status_pengajuan', self::APPROVED)
            ->where('cuti_roster.status_pengajuan_hrd', self::APPROVED)
            ->where('periode_kerja_roster.tipe_rencana', self::TYPE_CUTI_ROSTER)
            ->whereNotNull('cuti_roster.tgl_mulai_cuti')
            ->whereNotNull('cuti_roster.tgl_mulai_cuti_berakhir');
    }
}
