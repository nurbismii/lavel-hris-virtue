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
        $scopedDepartemenIds = $user->scopedDepartmentIds();
        $scopedDivisiIds = $user->scopedDivisionIds();
        $isDepartmentScoped = !$user->canAccessAllEmployees() && !empty($scopedDepartemenIds);
        $isDivisionScoped = $user->isDivisionScopedRole() && !empty($scopedDivisiIds);
        $isDepartmentReadonly = $isDepartmentScoped && count($scopedDepartemenIds) === 1;

        $start = Carbon::createFromFormat('Y-m', $periode)->day(16)->subMonth();
        $end   = Carbon::createFromFormat('Y-m', $periode)->day(15);

        $dates = [];
        $temp = $start->copy();
        while ($temp <= $end) {
            $dates[] = $temp->copy();
            $temp->addDay();
        }

        $departemens = $isDepartmentScoped
            ? Departemen::with('perusahaan')
                ->whereIn('id', $scopedDepartemenIds)
                ->orderBy('departemen')
                ->get()
            : Departemen::with('perusahaan')
                ->orderBy('departemen')
                ->get();

        $selectedDepartemenId = (string) $request->departemen;

        if ($isDepartmentScoped) {
            $selectedDepartemenId = in_array($selectedDepartemenId, $scopedDepartemenIds, true)
                ? $selectedDepartemenId
                : (string) ($scopedDepartemenIds[0] ?? '');
        }

        if ($selectedDepartemenId === '') {
            $selectedDepartemenId = null;
        }

        $departemen = $selectedDepartemenId
            ? $departemens->firstWhere('id', $selectedDepartemenId) ?? Departemen::find($selectedDepartemenId)
            : null;

        $divisis = $selectedDepartemenId
            ? Divisi::query()
                ->where('departemen_id', $selectedDepartemenId)
                ->when($isDivisionScoped, fn($query) => $query->whereIn('id', $scopedDivisiIds))
                ->orderBy('nama_divisi')
                ->get()
            : collect();

        $isDivisionReadonly = $isDivisionScoped && $divisis->count() === 1 && $isDepartmentReadonly;
        $selectedDivisiId = (string) $request->divisi;

        if ($isDivisionScoped) {
            $allowedDivisiIds = $divisis->pluck('id')->map(fn($id) => (string) $id)->all();
            $selectedDivisiId = in_array($selectedDivisiId, $allowedDivisiIds, true)
                ? $selectedDivisiId
                : ($isDivisionReadonly ? (string) optional($divisis->first())->id : '');
        }

        if ($selectedDivisiId === '') {
            $selectedDivisiId = null;
        }

        $employees = collect();
        $offData = collect();

        if ($selectedDepartemenId) {
            $employees = Employee::with(['divisi', 'departemen'])
                ->where('departemen_id', $selectedDepartemenId)
                ->where('status_resign', 'AKTIF')
                ->when($isDivisionScoped, fn($query) => $query->whereIn('divisi_id', $scopedDivisiIds));

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
            'isDepartmentScoped',
            'isDivisionScoped',
            'isDepartmentReadonly',
            'isDivisionReadonly'
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

        $allowedEmployeeIds = Auth::user()
            ->applyEmployeeScope(Employee::query())
            ->pluck('nik')
            ->all();

        DB::transaction(function () use ($rows, $allowedEmployeeIds) {

            foreach ($rows as $row) {
                if (!in_array($row['employee_id'], $allowedEmployeeIds, true)) {
                    continue;
                }

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
