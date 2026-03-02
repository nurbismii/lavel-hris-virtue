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

class AttendanceSettingController extends Controller
{
    public function index(Request $request)
    {
        $periode = $request->periode ?? now()->format('Y-m');
        $user = Auth::user();

        $departemenId = optional($user->employee)->departemen_id;

        $start = Carbon::createFromFormat('Y-m', $periode)->day(16)->subMonth();
        $end   = Carbon::createFromFormat('Y-m', $periode)->day(15);

        $dates = [];
        $temp = $start->copy();
        while ($temp <= $end) {
            $dates[] = $temp->copy();
            $temp->addDay();
        }

        if (!$departemenId) {
            return view('admin-divisi.set-kehadiran.index', [
                'employees' => collect(),
                'dates' => $dates,
                'offData' => collect(),
                'periode' => $periode,
                'departemen' => null,
                'divisis' => collect(),
                'departemens' => collect(),
            ]);
        }

        $employees = Employee::with(['divisi', 'departemen'])
            ->where('departemen_id', $departemenId);

        if ($request->divisi) {
            $employees->where('divisi_id', $request->divisi);
        }

        $employees = $employees
            ->orderBy('nama_karyawan')
            ->get();

        $offData = EmployeeAttendanceSetting::whereBetween('tanggal', [
            $start->toDateString(),
            $end->toDateString()
        ])
            ->whereIn('employee_id', $employees->pluck('nik'))
            ->get()
            ->groupBy('employee_id');

        $departemen = Departemen::find($departemenId);

        $divisis = Divisi::where('departemen_id', $departemenId)
            ->orderBy('nama_divisi')
            ->get();

        return view('admin-divisi.set-kehadiran.index', compact(
            'employees',
            'dates',
            'offData',
            'periode',
            'departemen',
            'divisis'
        ));
    }

    public function update(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,nik',
            'tanggal' => 'required|date',
            'status' => 'required|in:HADIR,OFF'
        ]);

        $periode = Carbon::parse($request->tanggal)->format('Y-m');

        if ($request->status === 'OFF') {

            EmployeeAttendanceSetting::updateOrCreate(
                [
                    'employee_id' => $request->employee_id,
                    'tanggal' => $request->tanggal,
                ],
                [
                    'status' => 'OFF',
                    'periode' => $periode
                ]
            );
        } else {

            EmployeeAttendanceSetting::where([
                'employee_id' => $request->employee_id,
                'tanggal' => $request->tanggal,
            ])->delete();
        }

        return response()->json(['success' => true]);
    }
}
