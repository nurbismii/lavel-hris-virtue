<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Carbon\Carbon;
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
        [$defaultStart, $defaultEnd] = $this->getDefaultCutoffPeriod();

        $start = request('start', $defaultStart->toDateString());
        $end   = request('end', $defaultEnd->toDateString());
        $dashboardService = app()->make(\App\Services\Dashboard\DashboardService::class);
        $summaryYear = Carbon::parse($end)->year;
        // ================ SUMMARY =================
        $totalAktif = Employee::where('status_resign', 'AKTIF')->whereIn('area_kerja', ['VDNI', 'VDNIP'])->count();
        // Area Kerja
        $areaKerja = $dashboardService->getAreaKerja();
        // Gender
        $gender = $dashboardService->getGender();
        // ================ MUTASI =================
        // Karyawan Masuk
        $masuk = $dashboardService->getKaryawanMasuk($start, $end);
        // Karyawan Keluar
        $keluar = $dashboardService->getKaryawanKeluar($start, $end);
        // ================ TAMBAHAN PENTING ================
        // Status karyawan
        $statusKaryawan = $dashboardService->getStatusKaryawan();
        // Divisi
        $divisi = $dashboardService->getDivisi();
        // Rentang umur
        $rentangUmur = $dashboardService->getRentangUmur();
        // Summary masuk keluar per bulan
        $summaryBulanan = $dashboardService->getSummaryMasukKeluarBulanan($summaryYear);
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
            'end',
            'rentangUmur',
            'summaryBulanan',
            'summaryYear'
        ));
    }

    private function getDefaultCutoffPeriod(): array
    {
        $today = Carbon::today();

        if ($today->day >= 16) {
            $start = Carbon::create($today->year, $today->month, 16);
            $end = (clone $start)->addMonth()->day(15);
        } else {
            $start = Carbon::create($today->year, $today->month, 16)->subMonth();
            $end = Carbon::create($today->year, $today->month, 15);
        }

        return [$start, $end];
    }
}
