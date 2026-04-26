<?php

namespace App\Services\Presensi;

use App\Models\Presensi;
use App\Models\Shift;
use App\Models\WorkPattern;
use Carbon\Carbon;

class AttendanceFulfillmentService
{
    private const LATE_TOLERANCE_MINUTES = 10;

    public function evaluate(?Presensi $presensi, $scheduleSource, $date = null): array
    {
        $scheduleData = $this->resolveScheduleData($scheduleSource, $date ?: optional($presensi)->tanggal);
        $expectedMinutes = $scheduleData['expected_work_minutes'];

        if (!$scheduleSource || $expectedMinutes === null) {
            return [
                'is_applicable' => false,
                'is_complete' => false,
                'badge_class' => 'secondary',
                'label' => 'Belum diatur',
                'description' => 'Rentang jam kerja pada pola kerja belum diatur.',
                'expected_minutes' => null,
                'actual_minutes' => null,
                'shortage_minutes' => null,
            ];
        }

        if (!$presensi || filled($presensi->status_presensi)) {
            return [
                'is_applicable' => false,
                'is_complete' => false,
                'badge_class' => 'secondary',
                'label' => '-',
                'description' => 'Hari ini tidak dinilai berdasarkan jam kerja.',
                'expected_minutes' => $expectedMinutes,
                'actual_minutes' => null,
                'shortage_minutes' => null,
            ];
        }

        if (!$presensi->jam_masuk || !$presensi->jam_pulang) {
            return [
                'is_applicable' => true,
                'is_complete' => false,
                'badge_class' => 'warning',
                'label' => 'Belum lengkap',
                'description' => 'Presensi masuk dan pulang harus lengkap untuk menghitung pemenuhan jam kerja.',
                'expected_minutes' => $expectedMinutes,
                'actual_minutes' => null,
                'shortage_minutes' => null,
            ];
        }

        $actualMinutes = $this->resolveActualWorkMinutes($presensi, $scheduleData);

        if ($actualMinutes >= $expectedMinutes) {
            return [
                'is_applicable' => true,
                'is_complete' => true,
                'badge_class' => 'success',
                'label' => 'Terpenuhi',
                'description' => sprintf(
                    'Durasi kerja terhitung %s dari target %s.',
                    $this->formatMinutes($actualMinutes),
                    $this->formatMinutes($expectedMinutes)
                ),
                'expected_minutes' => $expectedMinutes,
                'actual_minutes' => $actualMinutes,
                'shortage_minutes' => 0,
            ];
        }

        $shortage = $expectedMinutes - $actualMinutes;

        return [
            'is_applicable' => true,
            'is_complete' => false,
            'badge_class' => 'danger',
            'label' => 'Kurang ' . $this->formatMinutes($shortage),
            'description' => sprintf(
                'Durasi kerja terhitung %s dari target %s.',
                $this->formatMinutes($actualMinutes),
                $this->formatMinutes($expectedMinutes)
            ),
            'expected_minutes' => $expectedMinutes,
            'actual_minutes' => $actualMinutes,
            'shortage_minutes' => $shortage,
        ];
    }

    public function resolveScheduleData($scheduleSource, $date = null): array
    {
        if (!$scheduleSource) {
            return [
                'expected_work_minutes' => null,
                'scheduled_break_minutes' => 0,
                'work_time_range_text' => 'Belum diatur',
                'break_time_range_text' => 'Tidak diatur',
                'expected_work_duration_text' => 'Belum diatur',
            ];
        }

        if (method_exists($scheduleSource, 'resolveScheduleForDate')) {
            return $scheduleSource->resolveScheduleForDate($date);
        }

        return [
            'start_time' => $scheduleSource->start_time,
            'end_time' => $scheduleSource->end_time,
            'break_start_time' => $scheduleSource->break_start_time,
            'break_end_time' => $scheduleSource->break_end_time,
            'expected_work_minutes' => $scheduleSource->expected_work_minutes,
            'scheduled_break_minutes' => $scheduleSource->scheduled_break_minutes,
            'work_time_range_text' => $scheduleSource->work_time_range_text,
            'break_time_range_text' => $scheduleSource->break_time_range_text,
            'expected_work_duration_text' => $scheduleSource->expected_work_duration_text,
        ];
    }

    private function resolveActualWorkMinutes(Presensi $presensi, array $scheduleData): int
    {
        $attendanceDate = Carbon::parse($presensi->tanggal ?: $presensi->jam_masuk)->toDateString();
        $actualStart = $this->parseActualDateTime($presensi->jam_masuk, $attendanceDate);
        $actualEnd = $this->parseActualDateTime($presensi->jam_pulang, $attendanceDate, $actualStart);

        if (!$this->hasScheduledWorkRange($scheduleData)) {
            return $this->resolveFallbackActualWorkMinutes($presensi, $scheduleData, $attendanceDate, $actualStart, $actualEnd);
        }

        [$scheduledStart, $scheduledEnd] = $this->buildScheduledWorkRange($scheduleData, $attendanceDate);

        if (!$this->hasScheduledBreakRange($scheduleData)) {
            return $this->calculateOverlapMinutes($scheduledStart, $scheduledEnd, $actualStart, $actualEnd);
        }

        [$scheduledBreakStart, $scheduledBreakEnd] = $this->buildScheduledBreakRange($scheduleData, $scheduledStart);

        if ($presensi->jam_istirahat && $presensi->jam_kembali_istirahat) {
            $actualBreakStart = $this->parseActualDateTime($presensi->jam_istirahat, $attendanceDate, $actualStart);
            $actualBreakEnd = $this->parseActualDateTime($presensi->jam_kembali_istirahat, $attendanceDate, $actualBreakStart);
            $effectiveActualStart = $this->applyLateTolerance($actualStart, $scheduledStart);
            $effectiveActualBreakEnd = $this->applyLateTolerance($actualBreakEnd, $scheduledBreakEnd);

            return $this->calculateOverlapMinutes($scheduledStart, $scheduledBreakStart, $effectiveActualStart, $actualBreakStart)
                + $this->calculateOverlapMinutes($scheduledBreakEnd, $scheduledEnd, $effectiveActualBreakEnd, $actualEnd);
        }

        $effectiveActualStart = $this->applyLateTolerance($actualStart, $scheduledStart);
        $grossScheduledPresence = $this->calculateOverlapMinutes($scheduledStart, $scheduledEnd, $actualStart, $actualEnd);
        $scheduledBreakOverlap = $this->calculateOverlapMinutes($actualStart, $actualEnd, $scheduledBreakStart, $scheduledBreakEnd);

        if ($effectiveActualStart->equalTo($actualStart)) {
            return max($grossScheduledPresence - $scheduledBreakOverlap, 0);
        }

        $toleratedPresence = $this->calculateOverlapMinutes($scheduledStart, $scheduledEnd, $effectiveActualStart, $actualEnd);
        $toleratedBreakOverlap = $this->calculateOverlapMinutes($effectiveActualStart, $actualEnd, $scheduledBreakStart, $scheduledBreakEnd);

        return max($toleratedPresence - $toleratedBreakOverlap, 0);
    }

    private function resolveFallbackActualWorkMinutes(Presensi $presensi, array $scheduleData, string $attendanceDate, Carbon $actualStart, Carbon $actualEnd): int
    {
        $grossMinutes = $actualStart->diffInMinutes($actualEnd);

        if ($presensi->jam_istirahat && $presensi->jam_kembali_istirahat) {
            $breakStart = $this->parseActualDateTime($presensi->jam_istirahat, $attendanceDate, $actualStart);
            $breakEnd = $this->parseActualDateTime($presensi->jam_kembali_istirahat, $attendanceDate, $breakStart);

            return max($grossMinutes - $this->calculateOverlapMinutes($actualStart, $actualEnd, $breakStart, $breakEnd), 0);
        }

        return max($grossMinutes - ($scheduleData['scheduled_break_minutes'] ?? 0), 0);
    }

    private function hasScheduledWorkRange(array $scheduleData): bool
    {
        return filled($scheduleData['start_time'] ?? null) && filled($scheduleData['end_time'] ?? null);
    }

    private function hasScheduledBreakRange(array $scheduleData): bool
    {
        return filled($scheduleData['break_start_time'] ?? null) && filled($scheduleData['break_end_time'] ?? null);
    }

    private function buildScheduledWorkRange(array $scheduleData, string $attendanceDate): array
    {
        $start = Carbon::createFromFormat('Y-m-d H:i:s', $attendanceDate . ' ' . $this->normalizeTime($scheduleData['start_time']));
        $end = Carbon::createFromFormat('Y-m-d H:i:s', $attendanceDate . ' ' . $this->normalizeTime($scheduleData['end_time']));

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    private function buildScheduledBreakRange(array $scheduleData, Carbon $scheduledStart): array
    {
        $breakStart = Carbon::createFromFormat('Y-m-d H:i:s', $scheduledStart->format('Y-m-d') . ' ' . $this->normalizeTime($scheduleData['break_start_time']));
        $breakEnd = Carbon::createFromFormat('Y-m-d H:i:s', $scheduledStart->format('Y-m-d') . ' ' . $this->normalizeTime($scheduleData['break_end_time']));

        if ($breakStart->lessThan($scheduledStart)) {
            $breakStart->addDay();
        }

        if ($breakEnd->lessThanOrEqualTo($breakStart)) {
            $breakEnd->addDay();
        }

        return [$breakStart, $breakEnd];
    }

    private function parseActualDateTime($value, string $attendanceDate, ?Carbon $notBefore = null): Carbon
    {
        $text = trim((string) $value);

        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $text)) {
            $dateTime = Carbon::createFromFormat('Y-m-d H:i:s', $attendanceDate . ' ' . $this->normalizeTime($text));
        } else {
            $dateTime = Carbon::parse($value);
        }

        while ($notBefore && $dateTime->lessThan($notBefore)) {
            $dateTime->addDay();
        }

        return $dateTime;
    }

    private function applyLateTolerance(Carbon $actualTime, Carbon $scheduledTime): Carbon
    {
        if (
            $actualTime->greaterThan($scheduledTime)
            && $scheduledTime->diffInMinutes($actualTime) <= self::LATE_TOLERANCE_MINUTES
        ) {
            return $scheduledTime->copy();
        }

        return $actualTime->copy();
    }

    private function normalizeTime(?string $time): string
    {
        $time = trim((string) $time);

        if (strlen($time) === 5) {
            return $time . ':00';
        }

        return $time;
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

    public function formatMinutes(?int $minutes): string
    {
        if ($minutes === null) {
            return '-';
        }

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
}
