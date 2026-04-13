<?php

namespace App\Http\Controllers\AdminDivisi;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\EmployeeAttendanceSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\Departemen;
use App\Models\Divisi;
use Illuminate\Support\Facades\DB;

class AttendanceSettingController extends Controller
{
    public function index(Request $request)
    {
        $periode = $request->periode ?? now()->format('Y-m');
        $user = Auth::user();
        $scopedDepartemenId = optional($user->employee)->departemen_id;
        $selectedDepartemenId = $scopedDepartemenId ?: $request->departemen;
        $selectedDivisiId = $request->divisi;
        $isDepartmentScoped = (bool) $scopedDepartemenId;

        $start = Carbon::createFromFormat('Y-m', $periode)->day(16)->subMonth();
        $end   = Carbon::createFromFormat('Y-m', $periode)->day(15);

        $dates = [];
        $temp = $start->copy();
        while ($temp <= $end) {
            $dates[] = $temp->copy();
            $temp->addDay();
        }

        $departemens = $isDepartmentScoped
            ? collect()
            : Departemen::with('perusahaan')
                ->orderBy('departemen')
                ->get();

        $departemen = $selectedDepartemenId
            ? Departemen::find($selectedDepartemenId)
            : null;

        $divisis = $selectedDepartemenId
            ? Divisi::where('departemen_id', $selectedDepartemenId)
            ->orderBy('nama_divisi')
            ->get()
            : collect();

        $employees = collect();
        $offData = collect();

        if ($selectedDepartemenId) {
            $employees = Employee::with(['divisi', 'departemen'])
                ->where('departemen_id', $selectedDepartemenId)
                ->where('status_resign', 'AKTIF');

            if ($selectedDivisiId) {
                $employees->where('divisi_id', $selectedDivisiId);
            }

            $employees = $employees
                ->orderBy('nama_karyawan')
                ->get();

            if ($employees->isNotEmpty()) {
                $offData = EmployeeAttendanceSetting::whereBetween('tanggal', [
                    $start->toDateString(),
                    $end->toDateString()
                ])
                    ->whereIn('employee_id', $employees->pluck('nik'))
                    ->get()
                    ->groupBy('employee_id');
            }
        }

        return view('admin-divisi.set-kehadiran.index', compact(
            'employees',
            'dates',
            'offData',
            'periode',
            'departemen',
            'departemens',
            'divisis',
            'start',
            'end',
            'selectedDepartemenId',
            'selectedDivisiId',
            'isDepartmentScoped'
        ));
    }

    public function update(Request $request)
    {
        $rows = $request->input('data');

        if (!$rows || !is_array($rows)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid payload'
            ], 400);
        }

        DB::transaction(function () use ($rows) {

            foreach ($rows as $row) {

                $periode = Carbon::parse($row['tanggal'])->format('Y-m');

                if ($row['status'] === 'OFF') {

                    EmployeeAttendanceSetting::updateOrCreate(
                        [
                            'employee_id' => $row['employee_id'],
                            'tanggal' => $row['tanggal'],
                        ],
                        [
                            'status' => 'OFF',
                            'periode' => $periode
                        ]
                    );
                } else {

                    EmployeeAttendanceSetting::where([
                        'employee_id' => $row['employee_id'],
                        'tanggal' => $row['tanggal'],
                    ])->delete();
                }
            }
        });

        return response()->json([
            'success' => true
        ]);
    }
}
