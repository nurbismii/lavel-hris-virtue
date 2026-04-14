<?php

namespace App\Services\Dashboard;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getAreaKerja()
    {
        // Area kerja
        $areaKerja = Employee::select('area_kerja', DB::raw('count(*) as total'))
            ->where('status_resign', 'AKTIF')
            ->groupBy('area_kerja')
            ->pluck('total', 'area_kerja');

        return $areaKerja;
    }

    public function getGender()
    {
        $gender = Employee::select('jenis_kelamin', DB::raw('count(*) as total'))
            ->where('status_resign', 'AKTIF')
            ->whereIn('area_kerja', ['VDNI', 'VDNIP'])
            ->groupBy('jenis_kelamin')
            ->pluck('total', 'jenis_kelamin');

        return $gender;
    }

    public function getKaryawanMasuk($start, $end)
    {
        $masuk = Employee::whereNotNull('entry_date')
            ->whereBetween('entry_date', [$start, $end])
            ->whereIn('area_kerja', ['VDNI', 'VDNIP'])
            ->count();

        return $masuk;
    }

    public function getKaryawanKeluar($start, $end)
    {
        $keluar = Employee::whereNotNull('status_resign')
            ->where('status_resign', '!=', 'AKTIF')
            ->whereIn('area_kerja', ['VDNI', 'VDNIP'])
            ->whereBetween('tgl_resign', [$start, $end])
            ->count();

        return $keluar;
    }

    public function getStatusKaryawan()
    {
        $statusKaryawan = Employee::select('status_resign', DB::raw('count(*) as total'))
            ->whereIn('area_kerja', ['VDNI', 'VDNIP'])
            ->groupBy('status_resign')
            ->pluck('total', 'status_resign');

        return $statusKaryawan;
    }

    public function getDivisi()
    {
        $divisi = Employee::select('divisi_id', DB::raw('count(*) as total'))
            ->where('status_resign', 'AKTIF')
            ->whereIn('area_kerja', ['VDNI', 'VDNIP'])
            ->groupBy('divisi_id')
            ->pluck('total', 'divisi_id');

        return $divisi;
    }

    public function getRentangUmur()
    {
        $ranges = [
            ['label' => '17-21', 'min' => 17, 'max' => 21],
            ['label' => '22-26', 'min' => 22, 'max' => 26],
            ['label' => '27-31', 'min' => 27, 'max' => 31],
            ['label' => '32-36', 'min' => 32, 'max' => 36],
            ['label' => '37-41', 'min' => 37, 'max' => 41],
            ['label' => '42-46', 'min' => 42, 'max' => 46],
            ['label' => '47-51', 'min' => 47, 'max' => 51],
            ['label' => '52-56', 'min' => 52, 'max' => 56],
            ['label' => '57+', 'min' => 57, 'max' => null],
        ];

        $summary = array_map(function ($range) {
            return [
                'label' => $range['label'],
                'total' => 0,
            ];
        }, $ranges);

        $birthDates = Employee::whereNotNull('tgl_lahir')
            ->where('status_resign', 'AKTIF')
            ->whereIn('area_kerja', ['VDNI', 'VDNIP'])
            ->pluck('tgl_lahir');

        foreach ($birthDates as $birthDate) {
            $age = Carbon::parse($birthDate)->age;

            foreach ($ranges as $index => $range) {
                $isMatch = $age >= $range['min'] && (is_null($range['max']) || $age <= $range['max']);

                if ($isMatch) {
                    $summary[$index]['total']++;
                    break;
                }
            }
        }

        return $summary;
    }

    public function getSummaryMasukKeluarBulanan(int $year)
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $summary = [];

        foreach ($months as $month => $label) {
            $periodStart = Carbon::create($year, $month, 1)->subMonthNoOverflow()->day(16)->startOfDay();
            $periodEnd = Carbon::create($year, $month, 15)->endOfDay();

            $masuk = Employee::whereNotNull('entry_date')
                ->whereIn('area_kerja', ['VDNI', 'VDNIP'])
                ->whereBetween('entry_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                ->count();

            $keluar = Employee::whereNotNull('status_resign')
                ->where('status_resign', '!=', 'AKTIF')
                ->whereIn('area_kerja', ['VDNI', 'VDNIP'])
                ->whereBetween('tgl_resign', [$periodStart->toDateString(), $periodEnd->toDateString()])
                ->count();

            $summary[] = [
                'label' => $label,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'masuk' => $masuk,
                'keluar' => $keluar,
            ];
        }

        return $summary;
    }
}
