<?php

namespace App\Services\Presensi;

use App\Models\OvertimeOrder;
use App\Models\Presensi;
use Carbon\Carbon;

class OvertimeOrderService
{
    public function getAcceptedOrderForDate(string $nikKaryawan, $tanggal): ?OvertimeOrder
    {
        if (!$nikKaryawan || !$tanggal) {
            return null;
        }

        return OvertimeOrder::query()
            ->with('requester')
            ->where('nik_karyawan', $nikKaryawan)
            ->whereDate('overtime_date', Carbon::parse($tanggal)->toDateString())
            ->where('employee_response_status', OvertimeOrder::RESPONSE_ACCEPTED)
            ->latest('id')
            ->first();
    }

    public function buildAcceptedAlphaMap(iterable $niks, $startDate, $endDate, array $existingPresensiMap = []): array
    {
        $niks = collect($niks)
            ->filter()
            ->values()
            ->all();

        if (empty($niks)) {
            return [];
        }

        $orders = OvertimeOrder::query()
            ->whereIn('nik_karyawan', $niks)
            ->accepted()
            ->inDateRange($startDate, $endDate)
            ->orderBy('overtime_date')
            ->get();
        $attendanceMap = $this->getAttendanceMap($niks, $startDate, $endDate);

        $alphaMap = [];

        foreach ($orders as $order) {
            $tanggal = $order->overtime_date->toDateString();

            if (!$this->isOrderWindowFinished($order)) {
                continue;
            }

            $presensi = $attendanceMap[$order->nik_karyawan][$tanggal] ?? null;

            if ($this->attendanceFulfillsOrder($order, $presensi)) {
                continue;
            }

            $alphaMap[$order->nik_karyawan][$tanggal] = [
                'status' => Presensi::shortStatus('Alpa'),
                'm' => null,
                'i' => null,
                'k' => null,
                'p' => null,
            ];
        }

        return $alphaMap;
    }

    public function buildAcceptedAlphaVirtualRows(string $nikKaryawan, $startDate, $endDate, array $existingDates = []): array
    {
        $orders = OvertimeOrder::query()
            ->where('nik_karyawan', $nikKaryawan)
            ->accepted()
            ->inDateRange($startDate, $endDate)
            ->orderByDesc('overtime_date')
            ->get();
        $attendanceMap = $this->getAttendanceMap([$nikKaryawan], $startDate, $endDate);

        return $orders
            ->filter(function (OvertimeOrder $order) use ($attendanceMap) {
                $dateString = $order->overtime_date->toDateString();
                $presensi = $attendanceMap[$order->nik_karyawan][$dateString] ?? null;

                return $this->isOrderWindowFinished($order)
                    && !$this->attendanceFulfillsOrder($order, $presensi);
            })
            ->map(function (OvertimeOrder $order) {
                return new Presensi([
                    'nik_karyawan' => $order->nik_karyawan,
                    'tanggal' => $order->overtime_date->toDateString(),
                    'status_presensi' => 'Alpa',
                    'jam_masuk' => null,
                    'jam_istirahat' => null,
                    'jam_kembali_istirahat' => null,
                    'jam_pulang' => null,
                ]);
            })
            ->all();
    }

    public function evaluateAttendance(OvertimeOrder $order, ?Presensi $presensi = null): array
    {
        if ($order->employee_response_status === OvertimeOrder::RESPONSE_REJECTED) {
            return [
                'status' => 'rejected',
                'label' => 'Tidak berlaku karena ditolak karyawan',
                'badge_class' => 'secondary',
            ];
        }

        if ($order->employee_response_status !== OvertimeOrder::RESPONSE_ACCEPTED) {
            return [
                'status' => 'pending_response',
                'label' => 'Menunggu respons karyawan',
                'badge_class' => 'warning',
            ];
        }

        if ($this->attendanceFulfillsOrder($order, $presensi)) {
            return [
                'status' => 'fulfilled',
                'label' => 'Hadir sesuai perintah',
                'badge_class' => 'success',
            ];
        }

        if ($this->isOrderWindowFinished($order)) {
            return [
                'status' => 'absent',
                'label' => 'Alpa',
                'badge_class' => 'danger',
            ];
        }

        return [
            'status' => 'waiting_attendance',
            'label' => 'Menunggu kehadiran',
            'badge_class' => 'info',
        ];
    }

    public function attendanceFulfillsOrder(OvertimeOrder $order, $presensi = null): bool
    {
        if (!$presensi || !$order->start_time || !$order->end_time) {
            return false;
        }

        [$requiredStart, $requiredEnd] = $this->resolveOrderRange($order);
        [$actualStart, $actualEnd] = $this->resolveAttendanceRange($presensi, $order->overtime_date);

        if (!$requiredStart || !$requiredEnd || !$actualStart || !$actualEnd) {
            return false;
        }

        return $actualStart->lessThanOrEqualTo($requiredStart)
            && $actualEnd->greaterThanOrEqualTo($requiredEnd);
    }

    private function getAttendanceMap(iterable $niks, $startDate, $endDate): array
    {
        $niks = collect($niks)
            ->filter()
            ->values()
            ->all();

        if (empty($niks)) {
            return [];
        }

        $map = [];

        Presensi::query()
            ->whereIn('nik_karyawan', $niks)
            ->whereBetween('tanggal', [
                Carbon::parse($startDate)->toDateString(),
                Carbon::parse($endDate)->toDateString(),
            ])
            ->get()
            ->each(function (Presensi $presensi) use (&$map) {
                $map[$presensi->nik_karyawan][$presensi->tanggal->toDateString()] = $presensi;
            });

        return $map;
    }

    private function isOrderWindowFinished(OvertimeOrder $order): bool
    {
        [, $requiredEnd] = $this->resolveOrderRange($order);

        return $requiredEnd
            ? now()->greaterThan($requiredEnd)
            : $order->isPastDate();
    }

    private function resolveOrderRange(OvertimeOrder $order): array
    {
        $baseDate = $order->overtime_date
            ? $order->overtime_date->toDateString()
            : now()->toDateString();
        $start = $this->parseDateTime($baseDate, $order->start_time);
        $end = $this->parseDateTime($baseDate, $order->end_time);

        if ($start && $end && $end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    private function resolveAttendanceRange($presensi, $baseDate): array
    {
        $baseDate = Carbon::parse($baseDate)->toDateString();
        $start = $this->parseDateTime($baseDate, $presensi->jam_masuk ?? null);
        $end = $this->parseDateTime($baseDate, $presensi->jam_pulang ?? null);

        if ($start && $end && $end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    private function parseDateTime($baseDate, $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->copy();
        }

        $value = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return Carbon::parse($value);
        }

        return Carbon::parse(Carbon::parse($baseDate)->toDateString() . ' ' . $value);
    }
}
