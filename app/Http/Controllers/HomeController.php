<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        // periode default (bulan sekarang)
        $start = request('start', now()->startOfMonth()->toDateString());
        $end   = request('end', now()->toDateString());
        // ================ SUMMARY =================
        $totalAktif = Employee::where('status_resign', 'AKTIF')->whereIn('area_kerja', ['VDNI', 'VDNIP'])->count();
        // Area Kerja
        $areaKerja = app()->make(\App\Services\Dashboard\DashboardService::class)->getAreaKerja();
        // Gender
        $gender = app()->make(\App\Services\Dashboard\DashboardService::class)->getGender();
        // ================ MUTASI =================
        // Karyawan Masuk
        $masuk = app()->make(\App\Services\Dashboard\DashboardService::class)->getKaryawanMasuk($start, $end);
        // Karyawan Keluar
        $keluar = app()->make(\App\Services\Dashboard\DashboardService::class)->getKaryawanKeluar($start, $end);
        // ================ TAMBAHAN PENTING ================
        // Status karyawan
        $statusKaryawan = app()->make(\App\Services\Dashboard\DashboardService::class)->getStatusKaryawan();
        // Divisi
        $divisi = app()->make(\App\Services\Dashboard\DashboardService::class)->getDivisi();
        // Turnover
        $turnover = $totalAktif > 0 ? round(($keluar / $totalAktif) * 100, 2) : 0;

        return view('home', compact(
            'totalAktif',
            'areaKerja',
            'gender',
            'masuk',
            'keluar',
            'statusKaryawan',
            'divisi',
            'turnover',
            'start',
            'end'
        ));
    }
}
