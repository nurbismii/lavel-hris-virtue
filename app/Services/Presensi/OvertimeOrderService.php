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

        $today = now()->toDateString();
        $orders = OvertimeOrder::query()
            ->whereIn('nik_karyawan', $niks)
            ->accepted()
            ->inDateRange($startDate, $endDate)
            ->orderBy('overtime_date')
            ->get();

        $alphaMap = [];

        foreach ($orders as $order) {
            $tanggal = $order->overtime_date->toDateString();

            if ($tanggal >= $today) {
                continue;
            }

            $presensi = $existingPresensiMap[$order->nik_karyawan][$tanggal] ?? null;

            if ($presensi && (
                !empty($presensi['status'])
                || !empty($presensi['m'])
                || !empty($presensi['i'])
                || !empty($presensi['k'])
                || !empty($presensi['p'])
            )) {
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
        $today = now()->toDateString();

        return OvertimeOrder::query()
            ->where('nik_karyawan', $nikKaryawan)
            ->accepted()
            ->inDateRange($startDate, $endDate)
            ->orderByDesc('overtime_date')
            ->get()
            ->filter(function (OvertimeOrder $order) use ($today, $existingDates) {
                $dateString = $order->overtime_date->toDateString();

                return $dateString < $today && !in_array($dateString, $existingDates, true);
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
}
