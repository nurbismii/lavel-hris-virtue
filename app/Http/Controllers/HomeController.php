<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\Karyawan\EmployeeMediaImportStatusService;
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
        $importStatusService = app()->make(EmployeeMediaImportStatusService::class);
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
        $uploadProgressStatuses = $this->decorateUploadProgressItems(
            $importStatusService->listForUser(request()->user(), ['photo', 'ktp', 'kk', 'sim', 'sio', 'face_reference'], 10)
        );

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
            'summaryYear',
            'uploadProgressStatuses'
        ));
    }

    public function uploadProgress(Request $request, EmployeeMediaImportStatusService $importStatusService)
    {
        return response()->json([
            'items' => $this->decorateUploadProgressItems(
                $importStatusService->listForUser($request->user(), ['photo', 'ktp', 'kk', 'sim', 'sio', 'face_reference'], 10)
            ),
        ]);
    }

    public function destroyUploadProgress(Request $request, string $importId, EmployeeMediaImportStatusService $importStatusService)
    {
        abort_unless($request->ajax(), 404);

        $deleted = $importStatusService->deleteForUser($request->user(), $importId);

        if (!$deleted) {
            return response()->json([
                'message' => 'Data progress tidak ditemukan atau tidak dapat dihapus.',
            ], 404);
        }

        return response()->json([
            'message' => 'Progress upload berhasil dihapus.',
        ]);
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

    private function decorateUploadProgressItems($items)
    {
        return collect($items)->map(function ($item) {
            $item['delete_url'] = route('home.upload-progress.destroy', $item['import_id']);

            return $item;
        })->values();
    }
}
