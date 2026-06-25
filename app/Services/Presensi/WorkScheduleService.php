<?php

namespace App\Services\Presensi;

use App\Models\Employee;
use App\Models\EmployeeAttendanceSetting;
use App\Models\NationalHoliday;
use App\Models\OvertimeOrder;
use App\Models\Presensi;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class WorkScheduleService
{
    public const STATUS_OFF = EmployeeAttendanceSetting::STATUS_OFF;
    public const STATUS_HADIR = EmployeeAttendanceSetting::STATUS_HADIR;

    protected static $hasNationalHolidayTable;
    protected static $nationalHolidayDateCache = [];
    private $rosterCyclePlanService;

    public function buildScheduleMap(Collection $employees, Collection $manualOverrides, $startDate, $endDate): array
    {
        $scheduleMap = [];
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        $supportsStatusColumn = EmployeeAttendanceSetting::supportsStatusColumn();
        $overrideMap = $manualOverrides
            ->groupBy('employee_id')
            ->map(fn(Collection $rows) => $rows->keyBy(fn($row) => Carbon::parse($row->tanggal)->toDateString()));

        $this->rosterCyclePlanService()->preloadApprovedCutiRosterDates($employees, $start, $end);

        foreach ($employees as $employee) {
            foreach (CarbonPeriod::create($start, $end) as $date) {
                $dateString = $date->toDateString();
                $autoStatus = $this->resolveAutoStatus($employee, $dateString);
                $employeeOverrides = $overrideMap->get($employee->nik, collect());
                $overrideRow = $employeeOverrides->get($dateString);
                $manualStatus = $supportsStatusColumn
                    ? optional($overrideRow)->status
                    : ($overrideRow ? self::STATUS_OFF : null);
                $finalStatus = $manualStatus ?: $autoStatus;
                $isManual = filled($manualStatus);

                $scheduleMap[$employee->nik][$dateString] = [
                    'auto_status' => $autoStatus,
                    'manual_status' => $manualStatus,
                    'final_status' => $finalStatus,
                    'is_manual' => $isManual,
                ];
            }
        }

        return $scheduleMap;
    }

    public function resolveFinalStatus(Employee $employee, $tanggal): string
    {
        $dateString = Carbon::parse($tanggal)->toDateString();
        $query = EmployeeAttendanceSetting::query()
            ->where('employee_id', $employee->nik)
            ->whereDate('tanggal', $dateString);

        if (EmployeeAttendanceSetting::supportsStatusColumn()) {
            $manualStatus = $query->value('status');
        } else {
            $manualStatus = $query->exists() ? self::STATUS_OFF : null;
        }

        return $manualStatus ?: $this->resolveAutoStatus($employee, $dateString);
    }

    public function applyManualOverride(Employee $employee, $tanggal, string $desiredStatus): void
    {
        $dateString = Carbon::parse($tanggal)->toDateString();
        $periodLockMessage = app(AttendancePeriodLockService::class)->guardDate($dateString, 'Pengaturan hari off');

        if ($periodLockMessage) {
            throw new RuntimeException($periodLockMessage);
        }

        $periode = Carbon::parse($dateString)->format('Y-m');
        $autoStatus = $this->resolveAutoStatus($employee, $dateString);
        $supportsStatusColumn = EmployeeAttendanceSetting::supportsStatusColumn();
        $supportsPeriodeColumn = EmployeeAttendanceSetting::supportsPeriodeColumn();
        $baseQuery = EmployeeAttendanceSetting::query()
            ->where('employee_id', $employee->nik)
            ->whereDate('tanggal', $dateString);

        if (!$supportsStatusColumn) {
            if ($desiredStatus === self::STATUS_HADIR) {
                if ($autoStatus === self::STATUS_OFF) {
                    throw new RuntimeException('Manual HADIR di atas AUTO OFF membutuhkan migrasi tabel setting hari off terbaru.');
                }

                $baseQuery->delete();

                return;
            }

            if ($desiredStatus === $autoStatus) {
                $baseQuery->delete();

                return;
            }

            $attributes = [
                'employee_id' => $employee->nik,
                'tanggal' => $dateString,
            ];
            $values = [];

            if ($supportsPeriodeColumn) {
                $values['periode'] = $periode;
            }

            if (empty($values)) {
                EmployeeAttendanceSetting::firstOrCreate($attributes);
            } else {
                EmployeeAttendanceSetting::updateOrCreate($attributes, $values);
            }

            return;
        }

        if ($desiredStatus === $autoStatus) {
            $baseQuery->delete();

            return;
        }

        $values = [
            'status' => $desiredStatus,
        ];

        if ($supportsPeriodeColumn) {
            $values['periode'] = $periode;
        }

        EmployeeAttendanceSetting::updateOrCreate(
            [
                'employee_id' => $employee->nik,
                'tanggal' => $dateString,
            ],
            $values
        );
    }

    public function buildVirtualOffRows(string $nikKaryawan, $startDate, $endDate, Collection $employees, array $existingDates = []): array
    {
        $employee = $employees->firstWhere('nik', $nikKaryawan);

        if (!$employee) {
            return [];
        }

        $manualOverrides = EmployeeAttendanceSetting::query()
            ->where('employee_id', $nikKaryawan)
            ->whereBetween('tanggal', [
                Carbon::parse($startDate)->toDateString(),
                Carbon::parse($endDate)->toDateString(),
            ])
            ->get();

        $scheduleMap = $this->buildScheduleMap(collect([$employee]), $manualOverrides, $startDate, $endDate);
        $acceptedOvertimeDates = OvertimeOrder::query()
            ->where('nik_karyawan', $nikKaryawan)
            ->accepted()
            ->inDateRange($startDate, $endDate)
            ->pluck('overtime_date')
            ->map(fn($date) => Carbon::parse($date)->toDateString())
            ->all();
        $rows = [];

        foreach (($scheduleMap[$nikKaryawan] ?? []) as $dateString => $schedule) {
            if (
                $schedule['final_status'] !== self::STATUS_OFF
                || in_array($dateString, $existingDates, true)
                || in_array($dateString, $acceptedOvertimeDates, true)
            ) {
                continue;
            }

            $rows[] = new Presensi([
                'nik_karyawan' => $nikKaryawan,
                'tanggal' => $dateString,
                'status_presensi' => $this->resolveDisplayStatusForOffDate($employee, $dateString),
            ]);
        }

        return $rows;
    }

    public function buildOffStatusMap(Collection $employees, $startDate, $endDate, array $existingPresensiMap = [], ?array $scheduleMap = null): array
    {
        if ($employees->isEmpty()) {
            return [];
        }

        if ($scheduleMap === null) {
            $manualOverrides = EmployeeAttendanceSetting::query()
                ->whereIn('employee_id', $employees->pluck('nik'))
                ->whereBetween('tanggal', [
                    Carbon::parse($startDate)->toDateString(),
                    Carbon::parse($endDate)->toDateString(),
                ])
                ->get();

            $scheduleMap = $this->buildScheduleMap($employees, $manualOverrides, $startDate, $endDate);
        }

        $acceptedOvertimeMap = OvertimeOrder::query()
            ->whereIn('nik_karyawan', $employees->pluck('nik'))
            ->accepted()
            ->inDateRange($startDate, $endDate)
            ->get()
            ->groupBy('nik_karyawan')
            ->map(fn(Collection $rows) => $rows->pluck('overtime_date')
                ->map(fn($date) => Carbon::parse($date)->toDateString())
                ->all());
        $offMap = [];

        foreach ($employees as $employee) {
            foreach (($scheduleMap[$employee->nik] ?? []) as $dateString => $schedule) {
                if ($schedule['final_status'] !== self::STATUS_OFF) {
                    continue;
                }

                if (in_array($dateString, $acceptedOvertimeMap->get($employee->nik, []), true)) {
                    continue;
                }

                $existing = $existingPresensiMap[$employee->nik][$dateString] ?? null;

                if ($existing && (
                    !empty($existing['status'])
                    || !empty($existing['m'])
                    || !empty($existing['i'])
                    || !empty($existing['k'])
                    || !empty($existing['p'])
                )) {
                    continue;
                }

                $offMap[$employee->nik][$dateString] = [
                    'status' => Presensi::shortStatus($this->resolveDisplayStatusForOffDate($employee, $dateString)),
                    'm' => null,
                    'i' => null,
                    'k' => null,
                    'p' => null,
                ];
            }
        }

        return $offMap;
    }

    public function resolveAutoStatus(Employee $employee, $tanggal): string
    {
        $pattern = $employee->workPattern;
        $startDate = $employee->work_pattern_start_date;
        $date = Carbon::parse($tanggal)->startOfDay();
        $dateString = $date->toDateString();

        if ($this->shouldTreatNationalHolidayAsOff($pattern) && $this->isNationalHoliday($date)) {
            return self::STATUS_OFF;
        }

        if (!$pattern || !$startDate) {
            return self::STATUS_HADIR;
        }

        $startDate = Carbon::parse($startDate)->startOfDay();

        if ($date->lt($startDate)) {
            return self::STATUS_HADIR;
        }

        if ($pattern->isWeeklyPattern()) {
            return $this->resolveWeeklyStatus($pattern, $date);
        }

        $cursor = $startDate->copy();

        $isWorkSegment = true;

        while ($cursor->lte($date)) {
            $durationValue = $isWorkSegment
                ? (int) $pattern->work_duration_value
                : (int) $pattern->off_duration_value;
            $durationUnit = $isWorkSegment
                ? $pattern->work_duration_unit
                : $pattern->off_duration_unit;

            $segmentEnd = $this->addDuration($cursor->copy(), $durationValue, $durationUnit)->subDay();

            if ($date->betweenIncluded($cursor, $segmentEnd)) {
                if (
                    !$isWorkSegment
                    && $this->rosterCyclePlanService()->isRosterCyclePattern($pattern)
                    && !$this->rosterCyclePlanService()->hasApprovedCutiRosterDate($employee, $dateString)
                ) {
                    return self::STATUS_HADIR;
                }

                return $isWorkSegment ? self::STATUS_HADIR : self::STATUS_OFF;
            }

            $cursor = $segmentEnd->copy()->addDay();
            $isWorkSegment = !$isWorkSegment;
        }

        return self::STATUS_HADIR;
    }

    public function isNationalHolidayDate($tanggal): bool
    {
        return $this->isNationalHoliday(Carbon::parse($tanggal)->startOfDay());
    }

    protected function isNationalHoliday(Carbon $date): bool
    {
        if (!$this->supportsNationalHolidayTable()) {
            return false;
        }

        $dateString = $date->toDateString();

        if (!array_key_exists($dateString, static::$nationalHolidayDateCache)) {
            static::$nationalHolidayDateCache[$dateString] = NationalHoliday::query()
                ->whereDate('holiday_date', $dateString)
                ->exists();
        }

        return static::$nationalHolidayDateCache[$dateString];
    }

    protected function supportsNationalHolidayTable(): bool
    {
        if (static::$hasNationalHolidayTable === null) {
            static::$hasNationalHolidayTable = Schema::hasTable((new NationalHoliday())->getTable());
        }

        return static::$hasNationalHolidayTable;
    }

    protected function resolveDisplayStatusForOffDate(Employee $employee, $tanggal): string
    {
        if (
            $this->rosterCyclePlanService()->isDateInRosterOffSegment($employee, $tanggal)
            && $this->rosterCyclePlanService()->hasApprovedCutiRosterDate($employee, $tanggal)
        ) {
            return AttendanceStatusService::STATUS_CUTI_ROSTER;
        }

        return $this->isNationalHolidayDate($tanggal)
            ? AttendanceStatusService::STATUS_LIBUR_NASIONAL
            : AttendanceStatusService::STATUS_OFF;
    }

    protected function shouldTreatNationalHolidayAsOff($pattern): bool
    {
        if (!$pattern) {
            return true;
        }

        return data_get($pattern, 'national_holiday_as_off', true) !== false;
    }

    private function resolveWeeklyStatus($pattern, Carbon $date): string
    {
        $weekdayMap = $pattern::weekdayIndexes();
        $workingIndexes = collect($pattern->normalizeWeeklyWorkDays())
            ->map(fn($day) => $weekdayMap[$day] ?? null)
            ->filter(fn($dayIndex) => $dayIndex !== null)
            ->values()
            ->all();

        if (empty($workingIndexes)) {
            return self::STATUS_HADIR;
        }

        return in_array($date->dayOfWeek, $workingIndexes, true)
            ? self::STATUS_HADIR
            : self::STATUS_OFF;
    }

    private function addDuration(Carbon $date, int $value, ?string $unit): Carbon
    {
        switch ($unit) {
            case 'week':
                return $date->addWeeks($value);
            case 'month':
                return $date->addMonthsNoOverflow($value);
            case 'day':
            default:
                return $date->addDays($value);
        }
    }

    private function rosterCyclePlanService(): RosterCyclePlanService
    {
        if (!$this->rosterCyclePlanService) {
            $this->rosterCyclePlanService = app(RosterCyclePlanService::class);
        }

        return $this->rosterCyclePlanService;
    }
}
