<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\NationalHoliday;
use App\Models\Perusahaan;
use App\Models\Presensi;
use App\Services\Presensi\OvertimeOrderService;
use App\Services\Presensi\WorkScheduleService;
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

        $dates = collect(CarbonPeriod::create($start, $end)->toArray());
        $nationalHolidaysByDate = NationalHoliday::query()
            ->whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn($holiday) => $holiday->holiday_date->toDateString());

        $tanggalHeaders = $dates
            ->map(fn($date) => $date->format('Y-m-d'))
            ->toArray();

        $tanggalMeta = $dates
            ->mapWithKeys(function ($date) use ($nationalHolidaysByDate) {
                $dateString = $date->format('Y-m-d');
                $holiday = $nationalHolidaysByDate->get($dateString);

                return [
                    $dateString => [
                        'day' => $date->translatedFormat('D'),
                        'is_sunday' => $date->isSunday(),
                        'is_national_holiday' => filled($holiday),
                        'holiday_name' => $holiday->holiday_name ?? null,
                    ],
                ];
            })
            ->toArray();

        $baseQuery = Employee::query()
            ->select('nik', 'nama_karyawan', 'departemen_id', 'divisi_id', 'work_pattern_id', 'work_pattern_start_date')
            ->where('status_resign', 'AKTIF')
            ->where('departemen_id', $request->departemen);

        if ($request->divisi) {
            $baseQuery->where('divisi_id', $request->divisi);
        }

        $length = $request->length ?? 10;
        $startPage = $request->start ?? 0;

        $employeePage = (clone $baseQuery)
            ->skip($startPage)
            ->take($length)
            ->with('workPattern')
            ->get();

        $niks = $employeePage->pluck('nik');

        $presensiRows = DB::table('absensis')
            ->whereIn('nik_karyawan', $niks)
            ->whereBetween('tanggal', [$start, $end])
            ->get();

        $verificationRows = DB::table('presensi_verifications')
            ->select('presensi_id', 'attendance_type', 'status')
            ->whereIn('presensi_id', $presensiRows->pluck('id')->filter()->values())
            ->get()
            ->groupBy('presensi_id');

        $presensiMap = [];

        foreach ($presensiRows as $p) {

            $tgl = Carbon::parse($p->tanggal)->format('Y-m-d');
            $verificationByType = $this->verificationStatusByType($verificationRows->get($p->id));

            $presensiMap[$p->nik_karyawan][$tgl] = [
                'status' => $p->status_presensi ? Presensi::shortStatus($p->status_presensi) : null,
                'm' => $p->status_presensi ? null : $this->formatAttendanceClock($p->jam_masuk, $tgl),
                'i' => $p->status_presensi ? null : $this->formatAttendanceClock($p->jam_istirahat, $tgl),
                'k' => $p->status_presensi ? null : $this->formatAttendanceClock($p->jam_kembali_istirahat, $tgl),
                'p' => $p->status_presensi ? null : $this->formatAttendanceClock($p->jam_pulang, $tgl),
                'm_status' => $p->status_presensi ? null : $this->verificationStatusForAction($verificationByType, 'masuk', $p->jam_masuk ? ($p->status_absen ?? null) : null),
                'i_status' => $p->status_presensi ? null : $this->verificationStatusForAction($verificationByType, 'istirahat', $p->jam_istirahat ? ($p->status_absen ?? null) : null),
                'k_status' => $p->status_presensi ? null : $this->verificationStatusForAction($verificationByType, 'kembali', $p->jam_kembali_istirahat ? ($p->status_absen ?? null) : null),
                'p_status' => $p->status_presensi ? null : $this->verificationStatusForAction($verificationByType, 'pulang', $p->jam_pulang ? ($p->status_absen ?? null) : null),
                'verification' => $p->status_absen ?? null,
            ];
        }

        $actualPresensiMap = $presensiMap;

        $offMap = app(WorkScheduleService::class)->buildOffStatusMap($employeePage, $start, $end, $presensiMap);

        foreach ($offMap as $nik => $dates) {
            foreach ($dates as $tanggal => $payload) {
                $presensiMap[$nik][$tanggal] = $payload;
            }
        }

        $alphaMap = app(OvertimeOrderService::class)->buildAcceptedAlphaMap($niks, $start, $end, $actualPresensiMap);

        foreach ($alphaMap as $nik => $dates) {
            foreach ($dates as $tanggal => $payload) {
                $presensiMap[$nik][$tanggal] = $payload;
            }
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
                'tanggalHeaders' => $tanggalHeaders,
                'tanggalMeta' => $tanggalMeta,
            ])

            ->make(true);
    }

    public function export(Request $request)
    {
        if (!$request->departemen) {
            return back()->with('error', 'Departemen wajib dipilih');
        }

        [$start, $end] = $this->generateCutoff($request->cutoff_month);

        $dates = collect(CarbonPeriod::create($start, $end)->toArray());

        $tanggalHeaders = $dates
            ->map(fn($date) => $date->format('Y-m-d'))
            ->toArray();

        $employeeQuery = Employee::query()
            ->select('nik', 'nama_karyawan', 'work_pattern_id', 'work_pattern_start_date')
            ->where('status_resign', 'AKTIF')
            ->where('departemen_id', $request->departemen);

        if ($request->divisi) {
            $employeeQuery->where('divisi_id', $request->divisi);
        }

        $employees = $employeeQuery
            ->with('workPattern')
            ->get();

        $niks = $employees->pluck('nik');

        $presensiRows = DB::table('absensis')
            ->whereIn('nik_karyawan', $niks)
            ->whereBetween('tanggal', [$start, $end])
            ->get();

        $verificationRows = DB::table('presensi_verifications')
            ->select('presensi_id', 'attendance_type', 'status')
            ->whereIn('presensi_id', $presensiRows->pluck('id')->filter()->values())
            ->get()
            ->groupBy('presensi_id');

        $presensiMap = [];

        foreach ($presensiRows as $p) {

            $tgl = Carbon::parse($p->tanggal)->format('Y-m-d');
            $verificationByType = $this->verificationStatusByType($verificationRows->get($p->id));

            $presensiMap[$p->nik_karyawan][$tgl] = [
                'status' => $p->status_presensi ? Presensi::shortStatus($p->status_presensi) : '',
                'm' => $p->status_presensi ? '' : $this->formatAttendanceClock($p->jam_masuk, $tgl),
                'i' => $p->status_presensi ? '' : $this->formatAttendanceClock($p->jam_istirahat, $tgl),
                'k' => $p->status_presensi ? '' : $this->formatAttendanceClock($p->jam_kembali_istirahat, $tgl),
                'p' => $p->status_presensi ? '' : $this->formatAttendanceClock($p->jam_pulang, $tgl),
                'm_status' => $p->status_presensi ? null : $this->verificationStatusForAction($verificationByType, 'masuk', $p->jam_masuk ? ($p->status_absen ?? null) : null),
                'i_status' => $p->status_presensi ? null : $this->verificationStatusForAction($verificationByType, 'istirahat', $p->jam_istirahat ? ($p->status_absen ?? null) : null),
                'k_status' => $p->status_presensi ? null : $this->verificationStatusForAction($verificationByType, 'kembali', $p->jam_kembali_istirahat ? ($p->status_absen ?? null) : null),
                'p_status' => $p->status_presensi ? null : $this->verificationStatusForAction($verificationByType, 'pulang', $p->jam_pulang ? ($p->status_absen ?? null) : null),
                'verification' => $p->status_absen ?? null,
            ];
        }

        $actualPresensiMap = $presensiMap;

        $offMap = app(WorkScheduleService::class)->buildOffStatusMap($employees, $start, $end, $presensiMap);

        foreach ($offMap as $nik => $dates) {
            foreach ($dates as $tanggal => $payload) {
                $presensiMap[$nik][$tanggal] = [
                    'status' => $payload['status'] ?? '',
                    'm' => $payload['m'] ?? '',
                    'i' => $payload['i'] ?? '',
                    'k' => $payload['k'] ?? '',
                    'p' => $payload['p'] ?? '',
                    'm_status' => null,
                    'i_status' => null,
                    'k_status' => null,
                    'p_status' => null,
                ];
            }
        }

        $alphaMap = app(OvertimeOrderService::class)->buildAcceptedAlphaMap($niks, $start, $end, $actualPresensiMap);

        foreach ($alphaMap as $nik => $dates) {
            foreach ($dates as $tanggal => $payload) {
                $presensiMap[$nik][$tanggal] = [
                    'status' => $payload['status'] ?? '',
                    'm' => $payload['m'] ?? '',
                    'i' => $payload['i'] ?? '',
                    'k' => $payload['k'] ?? '',
                    'p' => $payload['p'] ?? '',
                    'm_status' => null,
                    'i_status' => null,
                    'k_status' => null,
                    'p_status' => null,
                ];
            }
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

                        $row[] = $p['status']
                            ? $p['status']
                            : $this->formatAttendanceExportCell($p);
                    } else {
                        $row[] = '';
                    }
                }

                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 'Presensi_' . now()->format('Ymd_His') . '.csv');
    }

    private function formatAttendanceClock(?string $value, string $attendanceDate): ?string
    {
        if (!$value) {
            return null;
        }

        $clock = Carbon::parse($value);
        $suffix = $clock->toDateString() > Carbon::parse($attendanceDate)->toDateString() ? ' +1' : '';

        return $clock->format('H:i') . $suffix;
    }

    private function verificationStatusByType($rows): array
    {
        if (!$rows) {
            return [];
        }

        return collect($rows)
            ->pluck('status', 'attendance_type')
            ->all();
    }

    private function verificationStatusForAction(array $verificationByType, string $type, ?string $fallback): ?string
    {
        return $verificationByType[$type] ?? $fallback;
    }

    private function formatAttendanceExportCell(array $presensi): string
    {
        $parts = [];
        $types = [
            ['label' => 'M', 'time' => 'm', 'status' => 'm_status'],
            ['label' => 'I', 'time' => 'i', 'status' => 'i_status'],
            ['label' => 'K', 'time' => 'k', 'status' => 'k_status'],
            ['label' => 'P', 'time' => 'p', 'status' => 'p_status'],
        ];

        foreach ($types as $type) {
            $time = $presensi[$type['time']] ?? null;

            if (!$time) {
                continue;
            }

            $status = $this->shortVerificationStatus($presensi[$type['status']] ?? null);
            $parts[] = trim($type['label'] . ' ' . $time . ($status ? ' (' . $status . ')' : ''));
        }

        return trim(implode(' ', $parts));
    }

    private function shortVerificationStatus(?string $status): ?string
    {
        switch ($status) {
            case Presensi::STATUS_ABSEN_VERIFIED:
                return 'SV';
            case Presensi::STATUS_ABSEN_PENDING_REVIEW:
                return 'RV';
            case Presensi::STATUS_ABSEN_REJECTED:
                return 'RJ';
            default:
                return null;
        }
    }
}
