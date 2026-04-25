<?php

namespace App\Services\Presensi;

use App\Models\Presensi;
use App\Models\Shift;
use App\Models\WorkPattern;
use Carbon\Carbon;

class AttendanceFulfillmentService
{
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
                    'Durasi hadir %s dari target %s.',
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
                'Durasi hadir %s dari target %s.',
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
            'expected_work_minutes' => $scheduleSource->expected_work_minutes,
            'scheduled_break_minutes' => $scheduleSource->scheduled_break_minutes,
            'work_time_range_text' => $scheduleSource->work_time_range_text,
            'break_time_range_text' => $scheduleSource->break_time_range_text,
            'expected_work_duration_text' => $scheduleSource->expected_work_duration_text,
        ];
    }

    private function resolveActualWorkMinutes(Presensi $presensi, array $scheduleData): int
    {
        $start = Carbon::parse($presensi->jam_masuk);
        $end = Carbon::parse($presensi->jam_pulang);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        $grossMinutes = $start->diffInMinutes($end);
        $breakMinutes = $this->resolveBreakMinutes($presensi, $scheduleData, $start, $end);

        return max($grossMinutes - $breakMinutes, 0);
    }

    private function resolveBreakMinutes(Presensi $presensi, array $scheduleData, Carbon $shiftStart, Carbon $shiftEnd): int
    {
        if ($presensi->jam_istirahat && $presensi->jam_kembali_istirahat) {
            $breakStart = Carbon::parse($presensi->jam_istirahat);
            $breakEnd = Carbon::parse($presensi->jam_kembali_istirahat);

            if ($breakStart->lessThan($shiftStart)) {
                $breakStart->addDay();
            }

            if ($breakEnd->lessThanOrEqualTo($breakStart)) {
                $breakEnd->addDay();
            }

            return $this->calculateOverlapMinutes($shiftStart, $shiftEnd, $breakStart, $breakEnd);
        }

        return $scheduleData['scheduled_break_minutes'] ?? 0;
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
