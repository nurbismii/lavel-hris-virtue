<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\Perusahaan;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class PresensiController extends Controller
{
    public function index()
    {
        $departemens = Departemen::with('perusahaan')
            ->orderBy('departemen')
            ->get();

        $divisis = Divisi::orderBy('nama_divisi')->get();
        $areas = Perusahaan::whereIn('kode_perusahaan', ['VDNI', 'VDNIP'])->orderBy('kode_perusahaan')->get();

        return view('admin.presensi.index', compact(
            'departemens',
            'divisis',
            'areas'
        ));
    }

    private function generateCutoff($month)
    {
        $start = Carbon::parse($month)->subMonth()->startOfMonth()->addDays(15);
        $end   = Carbon::parse($month)->startOfMonth()->addDays(14);

        return [$start, $end];
    }

    public function dataPresensi(Request $request)
    {
        if (!$request->departemen) {
            return response()->json([
                "data" => [],
                "recordsTotal" => 0,
                "recordsFiltered" => 0,
                "tanggalHeaders" => []
            ]);
        }

        [$start, $end] = $this->generateCutoff($request->cutoff_month);

        $period = CarbonPeriod::create($start, $end);

        $tanggalHeaders = collect($period)
            ->map(fn($date) => $date->format('Y-m-d'))
            ->toArray();

        $baseQuery = Employee::query()
            ->select('nik', 'nama_karyawan', 'departemen_id', 'divisi_id')
            ->where('status_resign', 'AKTIF')
            ->where('departemen_id', $request->departemen);

        if ($request->divisi) {
            $baseQuery->where('divisi_id', $request->divisi);
        }

        $length = $request->length ?? 10;
        $startPage = $request->start ?? 0;

        $niks = (clone $baseQuery)
            ->skip($startPage)
            ->take($length)
            ->pluck('nik');

        $presensiRows = DB::table('absensis')
            ->whereIn('nik_karyawan', $niks)
            ->whereBetween('tanggal', [$start, $end])
            ->get();

        $presensiMap = [];

        foreach ($presensiRows as $p) {

            $tgl = Carbon::parse($p->tanggal)->format('Y-m-d');

            $presensiMap[$p->nik_karyawan][$tgl] = [
                'm' => $p->jam_masuk ? Carbon::parse($p->jam_masuk)->format('H:i') : null,
                'i' => $p->jam_istirahat ? Carbon::parse($p->jam_istirahat)->format('H:i') : null,
                'k' => $p->jam_kembali_istirahat ? Carbon::parse($p->jam_kembali_istirahat)->format('H:i') : null,
                'p' => $p->jam_pulang ? Carbon::parse($p->jam_pulang)->format('H:i') : null,
            ];
        }

        return DataTables::of($baseQuery)

            ->addColumn('nik_karyawan', fn($row) => $row->nik)
            ->addColumn('nama_karyawan', fn($row) => $row->nama_karyawan)
            ->addColumn('tanggal_data', function ($row) use ($tanggalHeaders, $presensiMap) {

                $data = [];

                foreach ($tanggalHeaders as $tgl) {
                    $data[$tgl] = $presensiMap[$row->nik][$tgl] ?? null;
                }

                return $data;
            })

            ->with([
                'tanggalHeaders' => $tanggalHeaders
            ])

            ->make(true);
    }

    public function export(Request $request)
    {
        if (!$request->departemen) {
            return back()->with('error', 'Departemen wajib dipilih');
        }

        [$start, $end] = $this->generateCutoff($request->cutoff_month);

        $period = CarbonPeriod::create($start, $end);

        $tanggalHeaders = collect($period)
            ->map(fn($date) => $date->format('Y-m-d'))
            ->toArray();

        $employeeQuery = Employee::query()
            ->select('nik', 'nama_karyawan')
            ->where('status_resign', 'AKTIF')
            ->where('departemen_id', $request->departemen);

        if ($request->divisi) {
            $employeeQuery->where('divisi_id', $request->divisi);
        }

        $employees = $employeeQuery->get();

        $niks = $employees->pluck('nik');

        $presensiRows = DB::table('absensis')
            ->whereIn('nik_karyawan', $niks)
            ->whereBetween('tanggal', [$start, $end])
            ->get();

        $presensiMap = [];

        foreach ($presensiRows as $p) {

            $tgl = Carbon::parse($p->tanggal)->format('Y-m-d');

            $presensiMap[$p->nik_karyawan][$tgl] = [
                'm' => $p->jam_masuk ? Carbon::parse($p->jam_masuk)->format('H:i') : '',
                'i' => $p->jam_istirahat ? Carbon::parse($p->jam_istirahat)->format('H:i') : '',
                'k' => $p->jam_kembali_istirahat ? Carbon::parse($p->jam_kembali_istirahat)->format('H:i') : '',
                'p' => $p->jam_pulang ? Carbon::parse($p->jam_pulang)->format('H:i') : '',
            ];
        }

        return response()->streamDownload(function () use ($employees, $tanggalHeaders, $presensiMap) {

            $handle = fopen('php://output', 'w');

            // HEADER
            $header = ['NIK', 'Nama'];

            foreach ($tanggalHeaders as $tgl) {
                $header[] = Carbon::parse($tgl)->format('d');
            }

            fputcsv($handle, $header);

            // ROW DATA
            foreach ($employees as $emp) {

                $row = [
                    $emp->nik,
                    $emp->nama_karyawan
                ];

                foreach ($tanggalHeaders as $tgl) {

                    if (isset($presensiMap[$emp->nik][$tgl])) {

                        $p = $presensiMap[$emp->nik][$tgl];

                        $row[] =
                            "$p[m] $p[i] $p[k] $p[p]";
                    } else {
                        $row[] = '';
                    }
                }

                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 'Presensi_' . now()->format('Ymd_His') . '.csv');
    }
}
