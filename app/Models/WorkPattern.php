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
    ];

    public const UNIT_DAY = 'day';
    public const UNIT_WEEK = 'week';
    public const UNIT_MONTH = 'month';

    public function employees()
    {
        return $this->hasMany(Employee::class, 'work_pattern_id');
    }

    public function getCycleSummaryAttribute(): string
    {
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
        if (!$this->break_start_time || !$this->break_end_time) {
            return 0;
        }

        [$shiftStart, $shiftEnd] = $this->buildShiftRange();
        [$breakStart, $breakEnd] = $this->buildBreakRange($shiftStart);

        return $this->calculateOverlapMinutes($shiftStart, $shiftEnd, $breakStart, $breakEnd);
    }

    public function getExpectedWorkMinutesAttribute(): ?int
    {
        if (!$this->start_time || !$this->end_time) {
            return null;
        }

        [$start, $end] = $this->buildShiftRange();
        $grossMinutes = $start->diffInMinutes($end);
        $netMinutes = max($grossMinutes - $this->scheduled_break_minutes, 0);

        return $netMinutes;
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

    public static function unitOptions(): array
    {
        return [
            self::UNIT_DAY => 'Hari',
            self::UNIT_WEEK => 'Minggu',
            self::UNIT_MONTH => 'Bulan',
        ];
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

    private function buildShiftRange(): array
    {
        $baseDate = Carbon::today();
        $start = Carbon::createFromFormat('Y-m-d H:i:s', $baseDate->format('Y-m-d') . ' ' . $this->normalizeTime($this->start_time));
        $end = Carbon::createFromFormat('Y-m-d H:i:s', $baseDate->format('Y-m-d') . ' ' . $this->normalizeTime($this->end_time));

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    private function buildBreakRange(Carbon $shiftStart): array
    {
        $breakStart = Carbon::createFromFormat('Y-m-d H:i:s', $shiftStart->format('Y-m-d') . ' ' . $this->normalizeTime($this->break_start_time));
        $breakEnd = Carbon::createFromFormat('Y-m-d H:i:s', $shiftStart->format('Y-m-d') . ' ' . $this->normalizeTime($this->break_end_time));

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

        return $overlapStart->diffInMinutes($overlapEnd);
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
