<?php

namespace App\Services\Presensi;

use App\Models\Employee;
use App\Models\Presensi;
use Carbon\Carbon;

class AttendanceDateResolverService
{
    private const POST_SHIFT_GRACE_HOURS = 4;

    public function resolve(Employee $employee, ?Carbon $now = null): array
    {
        $now = $now ? $now->copy() : now();
        $today = $now->toDateString();
        $yesterday = $now->copy()->subDay()->toDateString();

        $previousPresensi = $this->findPresensi($employee, $yesterday);

        if ($this->shouldContinuePreviousAttendance($employee, $previousPresensi, $now)) {
            return $this->buildContext($employee, $yesterday, $previousPresensi, $now, true);
        }

        return $this->buildContext(
            $employee,
            $today,
            $this->findPresensi($employee, $today),
            $now,
            false
        );
    }

    public function resolveScheduleContext(Employee $employee, $date): array
    {
        $dateString = Carbon::parse($date)->toDateString();
        $employee->loadMissing('workPattern');
        $shift = app(ShiftAssignmentService::class)->resolveShiftForDate($employee, $dateString);
        $scheduleSource = $shift ?: $employee->workPattern;
        $scheduleData = app(AttendanceFulfillmentService::class)->resolveScheduleData($scheduleSource, $dateString);

        return [
            'date' => $dateString,
            'shift' => $shift,
            'schedule_source' => $scheduleSource,
            'schedule_data' => $scheduleData,
        ];
    }

    private function shouldContinuePreviousAttendance(Employee $employee, ?Presensi $presensi, Carbon $now): bool
    {
        if (
            !$presensi
            || filled($presensi->status_presensi)
            || !$presensi->jam_masuk
            || $presensi->jam_pulang
        ) {
            return false;
        }

        [, $scheduledEnd] = $this->resolveScheduledRange($employee, $presensi->tanggal);

        if (!$scheduledEnd) {
            $fallbackLimit = Carbon::parse($presensi->tanggal)->addDay()->addHours(self::POST_SHIFT_GRACE_HOURS);

            return $now->lessThanOrEqualTo($fallbackLimit);
        }

        return $now->lessThanOrEqualTo($scheduledEnd->copy()->addHours(self::POST_SHIFT_GRACE_HOURS));
    }

    private function buildContext(Employee $employee, string $date, ?Presensi $presensi, Carbon $now, bool $isCrossDay): array
    {
        $scheduleContext = $this->resolveScheduleContext($employee, $date);
        [$scheduledStart, $scheduledEnd] = $this->resolveScheduledRange($employee, $date, $scheduleContext['schedule_data']);

        return array_merge($scheduleContext, [
            'presensi' => $presensi,
            'now' => $now,
            'is_cross_day' => $isCrossDay,
            'scheduled_start' => $scheduledStart,
            'scheduled_end' => $scheduledEnd,
        ]);
    }

    private function resolveScheduledRange(Employee $employee, $date, ?array $scheduleData = null): array
    {
        $dateString = Carbon::parse($date)->toDateString();
        $scheduleData = $scheduleData ?: $this->resolveScheduleContext($employee, $dateString)['schedule_data'];
        $startTime = $scheduleData['start_time'] ?? null;
        $endTime = $scheduleData['end_time'] ?? null;

        if (!$startTime || !$endTime) {
            return [null, null];
        }

        $start = Carbon::parse($dateString . ' ' . $this->normalizeTime($startTime));
        $end = Carbon::parse($dateString . ' ' . $this->normalizeTime($endTime));

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    private function findPresensi(Employee $employee, string $date): ?Presensi
    {
        return Presensi::query()
            ->where('nik_karyawan', $employee->nik)
            ->whereDate('tanggal', $date)
            ->first();
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
