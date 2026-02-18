<?php

namespace App\Services\Dashboard;

use App\Models\Employee;
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
}
