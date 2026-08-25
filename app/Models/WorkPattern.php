<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class WorkPattern extends Model
{
    protected $table = 'work_patterns';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'national_holiday_as_off' => 'boolean',
        'weekly_work_days' => 'array',
    ];

    public const BASIS_CYCLE = 'cycle';
    public const BASIS_WEEKLY = 'weekly';
    public const UNIT_DAY = 'day';
    public const UNIT_WEEK = 'week';
    public const UNIT_MONTH = 'month';
    public const WEEKDAY_MONDAY = 'mon';
    public const WEEKDAY_TUESDAY = 'tue';
    public const WEEKDAY_WEDNESDAY = 'wed';
    public const WEEKDAY_THURSDAY = 'thu';
    public const WEEKDAY_FRIDAY = 'fri';
    public const WEEKDAY_SATURDAY = 'sat';
    public const WEEKDAY_SUNDAY = 'sun';

    public function employees()
    {
        return $this->hasMany(Employee::class, 'work_pattern_id');
    }

    public function getCycleSummaryAttribute(): string
    {
        if ($this->isWeeklyPattern()) {
            $workDaysCount = count($this->weekly_work_days ?: []);
            $offDaysCount = max(7 - $workDaysCount, 0);

            return sprintf(
                '%d:%d hari kerja mingguan (%s)',
                $workDaysCount,
                $offDaysCount,
                $this->weekly_work_days_text
            );
        }

        $workValue = (int) $this->work_duration_value;
        $offValue = (int) $this->off_duration_value;

        return sprintf(
            '%d %s kerja / %d %s off',
            $workValue,
            $this->formatUnitLabel($workValue, $this->work_duration_unit),
            $offValue,
            $this->formatUnitLabel($offValue, $this->off_duration_unit)
        );
    }

    public function getPatternBasisLabelAttribute(): string
    {
        return static::basisOptions()[$this->pattern_basis ?: static::BASIS_CYCLE] ?? 'Siklus';
    }

    public function getWeeklyWorkDaysTextAttribute(): string
    {
        $days = collect($this->normalizeWeeklyWorkDays($this->weekly_work_days))
            ->map(fn(string $day) => static::weekdayOptions()[$day] ?? strtoupper($day))
            ->values()
            ->all();

        return empty($days) ? 'Belum diatur' : implode(', ', $days);
    }

    public function getWorkTimeRangeTextAttribute(): string
    {
        if (!$this->start_time || !$this->end_time) {
            return 'Belum diatur';
        }

        return sprintf(
            '%s - %s',
            $this->formatTime($this->start_time),
            $this->formatTime($this->end_time)
        );
    }

    public function getBreakTimeRangeTextAttribute(): string
    {
        if (!$this->break_start_time || !$this->break_end_time) {
            return 'Tidak diatur';
        }

        return sprintf(
            '%s - %s',
            $this->formatTime($this->break_start_time),
            $this->formatTime($this->break_end_time)
        );
    }

    public function getScheduledBreakMinutesAttribute(): int
    {
        return $this->buildScheduleData(
            $this->start_time,
            $this->end_time,
            $this->break_start_time,
            $this->break_end_time
        )['scheduled_break_minutes'];
    }

    public function getExpectedWorkMinutesAttribute(): ?int
    {
        return $this->buildScheduleData(
            $this->start_time,
            $this->end_time,
            $this->break_start_time,
            $this->break_end_time
        )['expected_work_minutes'];
    }

    public function getExpectedWorkDurationTextAttribute(): string
    {
        $minutes = $this->expected_work_minutes;

        if ($minutes === null) {
            return 'Belum diatur';
        }

        return $this->formatMinutes($minutes);
    }

    public function getScheduledBreakDurationTextAttribute(): string
    {
        if (!$this->break_start_time || !$this->break_end_time) {
            return 'Tidak diatur';
        }

        return $this->formatMinutes($this->scheduled_break_minutes);
    }

    public function getSixthDayWorkTimeRangeTextAttribute(): string
    {
        return $this->buildScheduleData(
            $this->sixth_day_start_time,
            $this->sixth_day_end_time,
            $this->sixth_day_break_start_time,
            $this->sixth_day_break_end_time
        )['work_time_range_text'];
    }

    public function getSixthDayBreakTimeRangeTextAttribute(): string
    {
        return $this->buildScheduleData(
            $this->sixth_day_start_time,
            $this->sixth_day_end_time,
            $this->sixth_day_break_start_time,
            $this->sixth_day_break_end_time
        )['break_time_range_text'];
    }

    public function getSixthDayScheduledBreakMinutesAttribute(): int
    {
        return $this->buildScheduleData(
            $this->sixth_day_start_time,
            $this->sixth_day_end_time,
            $this->sixth_day_break_start_time,
            $this->sixth_day_break_end_time
        )['scheduled_break_minutes'];
    }

    public function getSixthDayExpectedWorkMinutesAttribute(): ?int
    {
        return $this->buildScheduleData(
            $this->sixth_day_start_time,
            $this->sixth_day_end_time,
            $this->sixth_day_break_start_time,
            $this->sixth_day_break_end_time
        )['expected_work_minutes'];
    }

    public function getSixthDayExpectedWorkDurationTextAttribute(): string
    {
        $minutes = $this->sixth_day_expected_work_minutes;

        if ($minutes === null) {
            return 'Belum diatur';
        }

        return $this->formatMinutes($minutes);
    }

    public function getNationalHolidayRuleLabelAttribute(): string
    {
        return ($this->national_holiday_as_off ?? true)
            ? 'Tanggal merah otomatis off'
            : 'Tanggal merah tetap masuk';
    }

    public static function unitOptions(): array
    {
        return [
            self::UNIT_DAY => 'Hari',
            self::UNIT_WEEK => 'Minggu',
            self::UNIT_MONTH => 'Bulan',
        ];
    }

    public static function basisOptions(): array
    {
        return [
            self::BASIS_WEEKLY => 'Hari kerja mingguan',
            self::BASIS_CYCLE => 'Siklus durasi (harian/mingguan/bulanan)',
        ];
    }

    public static function weekdayOptions(): array
    {
        return [
            self::WEEKDAY_MONDAY => 'Senin',
            self::WEEKDAY_TUESDAY => 'Selasa',
            self::WEEKDAY_WEDNESDAY => 'Rabu',
            self::WEEKDAY_THURSDAY => 'Kamis',
            self::WEEKDAY_FRIDAY => 'Jumat',
            self::WEEKDAY_SATURDAY => 'Sabtu',
            self::WEEKDAY_SUNDAY => 'Minggu',
        ];
    }

    public static function weekdayIndexes(): array
    {
        return [
            self::WEEKDAY_MONDAY => Carbon::MONDAY,
            self::WEEKDAY_TUESDAY => Carbon::TUESDAY,
            self::WEEKDAY_WEDNESDAY => Carbon::WEDNESDAY,
            self::WEEKDAY_THURSDAY => Carbon::THURSDAY,
            self::WEEKDAY_FRIDAY => Carbon::FRIDAY,
            self::WEEKDAY_SATURDAY => Carbon::SATURDAY,
            self::WEEKDAY_SUNDAY => Carbon::SUNDAY,
        ];
    }

    public function isWeeklyPattern(): bool
    {
        return ($this->pattern_basis ?: static::BASIS_CYCLE) === static::BASIS_WEEKLY;
    }

    public function hasSixthDaySchedule(): bool
    {
        return filled($this->sixth_day_start_time) && filled($this->sixth_day_end_time);
    }

    public function normalizeWeeklyWorkDays($days = null): array
    {
        $allowed = array_keys(static::weekdayOptions());
        $days = collect($days ?? $this->weekly_work_days ?? [])
            ->filter(fn($day) => in_array($day, $allowed, true))
            ->unique()
            ->sortBy(fn($day) => array_search($day, $allowed, true))
            ->values()
            ->all();

        return $days;
    }

    public function isSixthWeeklyWorkday($date): bool
    {
        if (!$this->isWeeklyPattern() || !$this->hasSixthDaySchedule()) {
            return false;
        }

        $weeklyWorkDays = $this->normalizeWeeklyWorkDays();

        if (count($weeklyWorkDays) < 6) {
            return false;
        }

        $weekdayMap = static::weekdayIndexes();
        $sixthWorkday = $weeklyWorkDays[5] ?? null;
        $targetDayIndex = $weekdayMap[$sixthWorkday] ?? null;

        if ($targetDayIndex === null) {
            return false;
        }

        return Carbon::parse($date)->dayOfWeek === $targetDayIndex;
    }

    public function resolveScheduleForDate($date = null): array
    {
        $useSixthDaySchedule = $date && $this->isSixthWeeklyWorkday($date);

        $schedule = $useSixthDaySchedule
            ? $this->buildScheduleData(
                $this->sixth_day_start_time,
                $this->sixth_day_end_time,
                $this->sixth_day_break_start_time,
                $this->sixth_day_break_end_time
            )
            : $this->buildScheduleData(
                $this->start_time,
                $this->end_time,
                $this->break_start_time,
                $this->break_end_time
            );

        $schedule['uses_sixth_day_schedule'] = $useSixthDaySchedule;

        return $schedule;
    }

    private function formatUnitLabel(int $value, ?string $unit): string
    {
        switch ($unit) {
            case self::UNIT_WEEK:
                return $value === 1 ? 'minggu' : 'minggu';
            case self::UNIT_MONTH:
                return $value === 1 ? 'bulan' : 'bulan';
            case self::UNIT_DAY:
            default:
                return $value === 1 ? 'hari' : 'hari';
        }
    }

    private function formatTime(?string $time): string
    {
        return Carbon::createFromFormat('H:i:s', $this->normalizeTime($time))->format('H:i');
    }

    private function buildScheduleData(?string $startTime, ?string $endTime, ?string $breakStartTime = null, ?string $breakEndTime = null): array
    {
        $workTimeRangeText = $this->buildTimeRangeText($startTime, $endTime, 'Belum diatur');
        $breakTimeRangeText = $this->buildTimeRangeText($breakStartTime, $breakEndTime, 'Tidak diatur');

        if (!$startTime || !$endTime) {
            return [
                'start_time' => $startTime,
                'end_time' => $endTime,
                'break_start_time' => $breakStartTime,
                'break_end_time' => $breakEndTime,
                'work_time_range_text' => $workTimeRangeText,
                'break_time_range_text' => $breakTimeRangeText,
                'scheduled_break_minutes' => 0,
                'expected_work_minutes' => null,
                'expected_work_duration_text' => 'Belum diatur',
                'scheduled_break_duration_text' => $breakTimeRangeText === 'Tidak diatur' ? 'Tidak diatur' : '0 menit',
            ];
        }

        [$shiftStart, $shiftEnd] = $this->buildShiftRange($startTime, $endTime);
        $grossMinutes = (int) $shiftStart->diffInMinutes($shiftEnd, true);
        $scheduledBreakMinutes = 0;

        if ($breakStartTime && $breakEndTime) {
            [$resolvedBreakStart, $resolvedBreakEnd] = $this->buildBreakRange($shiftStart, $breakStartTime, $breakEndTime);
            $scheduledBreakMinutes = $this->calculateOverlapMinutes($shiftStart, $shiftEnd, $resolvedBreakStart, $resolvedBreakEnd);
        }

        $expectedWorkMinutes = max($grossMinutes - $scheduledBreakMinutes, 0);

        return [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'break_start_time' => $breakStartTime,
            'break_end_time' => $breakEndTime,
            'work_time_range_text' => $workTimeRangeText,
            'break_time_range_text' => $breakTimeRangeText,
            'scheduled_break_minutes' => $scheduledBreakMinutes,
            'expected_work_minutes' => $expectedWorkMinutes,
            'expected_work_duration_text' => $this->formatMinutes($expectedWorkMinutes),
            'scheduled_break_duration_text' => ($breakStartTime && $breakEndTime)
                ? $this->formatMinutes($scheduledBreakMinutes)
                : 'Tidak diatur',
        ];
    }

    private function buildTimeRangeText(?string $startTime, ?string $endTime, string $emptyLabel): string
    {
        if (!$startTime || !$endTime) {
            return $emptyLabel;
        }

        return sprintf(
            '%s - %s',
            $this->formatTime($startTime),
            $this->formatTime($endTime)
        );
    }

    private function buildShiftRange(?string $startTime = null, ?string $endTime = null): array
    {
        $baseDate = Carbon::today();
        $start = Carbon::createFromFormat('Y-m-d H:i:s', $baseDate->format('Y-m-d') . ' ' . $this->normalizeTime($startTime ?: $this->start_time));
        $end = Carbon::createFromFormat('Y-m-d H:i:s', $baseDate->format('Y-m-d') . ' ' . $this->normalizeTime($endTime ?: $this->end_time));

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    private function buildBreakRange(Carbon $shiftStart, ?string $breakStartTime = null, ?string $breakEndTime = null): array
    {
        $breakStart = Carbon::createFromFormat('Y-m-d H:i:s', $shiftStart->format('Y-m-d') . ' ' . $this->normalizeTime($breakStartTime ?: $this->break_start_time));
        $breakEnd = Carbon::createFromFormat('Y-m-d H:i:s', $shiftStart->format('Y-m-d') . ' ' . $this->normalizeTime($breakEndTime ?: $this->break_end_time));

        if ($breakStart->lessThan($shiftStart)) {
            $breakStart->addDay();
        }

        if ($breakEnd->lessThanOrEqualTo($breakStart)) {
            $breakEnd->addDay();
        }

        return [$breakStart, $breakEnd];
    }

    private function calculateOverlapMinutes(Carbon $rangeStart, Carbon $rangeEnd, Carbon $windowStart, Carbon $windowEnd): int
    {
        $overlapStart = $rangeStart->greaterThan($windowStart) ? $rangeStart->copy() : $windowStart->copy();
        $overlapEnd = $rangeEnd->lessThan($windowEnd) ? $rangeEnd->copy() : $windowEnd->copy();

        if ($overlapEnd->lessThanOrEqualTo($overlapStart)) {
            return 0;
        }

        return (int) $overlapStart->diffInMinutes($overlapEnd, true);
    }

    private function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0 && $remainingMinutes > 0) {
            return "{$hours} jam {$remainingMinutes} menit";
        }

        if ($hours > 0) {
            return "{$hours} jam";
        }

        return "{$remainingMinutes} menit";
    }

    private function normalizeTime(?string $time): string
    {
        $time = trim((string) $time);

        if (strlen($time) === 5) {
            return $time . ':00';
        }

        return $time;
    }
}
