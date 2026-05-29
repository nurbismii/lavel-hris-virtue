<?php

namespace App\Http\Controllers\AdminDivisi;

use App\Http\Controllers\Controller;
use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\Employee;
use App\Models\NationalHoliday;
use App\Models\Shift;
use App\Services\Presensi\ShiftAssignmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ShiftSettingController extends Controller
{
    private const MATRIX_EMPLOYEE_LIMIT = 500;
    private const MAX_UPDATE_ROWS = self::MATRIX_EMPLOYEE_LIMIT * 32;

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
        $end = Carbon::createFromFormat('Y-m', $periode)->day(15);

        $dates = [];
        $temp = $start->copy();
        while ($temp <= $end) {
            $dates[] = $temp->copy();
            $temp->addDay();
        }

        $nationalHolidayMap = Schema::hasTable('national_holidays')
            ? NationalHoliday::query()
                ->whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])
                ->orderBy('holiday_date')
                ->get()
                ->keyBy(fn($holiday) => $holiday->holiday_date->toDateString())
            : collect();

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

        $requiresDivisionFilter = $selectedDepartemenId && !$selectedDivisiId && $divisis->count() > 1;
        $employeeLimitExceeded = false;
        $matrixEmployeeLimit = self::MATRIX_EMPLOYEE_LIMIT;
        $employees = collect();
        $shiftAssignmentMap = [];

        if ($selectedDepartemenId && !$requiresDivisionFilter) {
            $employees = Employee::with(['divisi', 'departemen', 'workPattern'])
                ->where('departemen_id', $selectedDepartemenId)
                ->where('status_resign', 'AKTIF')
                ->when($isDivisionScoped, fn($query) => $query->whereIn('divisi_id', $scopedDivisiIds));

            if ($selectedDivisiId) {
                $employees->where('divisi_id', $selectedDivisiId);
            }

            $employees = $employees
                ->orderBy('nama_karyawan')
                ->limit($matrixEmployeeLimit + 1)
                ->get();

            if ($employees->count() > $matrixEmployeeLimit) {
                $employeeLimitExceeded = true;
                $employees = $employees->take($matrixEmployeeLimit)->values();
            }

            $shiftAssignmentMap = app(ShiftAssignmentService::class)->buildAssignmentMap(
                $employees,
                $start,
                $end
            );
        }

        return view('admin-divisi.set-shift.index', [
            'employees' => $employees,
            'dates' => $dates,
            'nationalHolidayMap' => $nationalHolidayMap,
            'periode' => $periode,
            'departemens' => $departemens,
            'divisis' => $divisis,
            'start' => $start,
            'end' => $end,
            'selectedDepartemenId' => $selectedDepartemenId,
            'selectedDivisiId' => $selectedDivisiId,
            'isDepartmentScoped' => $isDepartmentScoped,
            'isDivisionScoped' => $isDivisionScoped,
            'isDepartmentReadonly' => $isDepartmentReadonly,
            'isDivisionReadonly' => $isDivisionReadonly,
            'requiresDivisionFilter' => $requiresDivisionFilter,
            'employeeLimitExceeded' => $employeeLimitExceeded,
            'matrixEmployeeLimit' => $matrixEmployeeLimit,
            'shiftAssignmentMap' => $shiftAssignmentMap,
            'shifts' => Shift::query()
                ->orderByRaw("FIELD(type, 'reguler', 'shift_1', 'shift_2', 'shift_3', 'custom')")
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request)
    {
        $rows = $request->input('data');

        if (!$rows || !is_array($rows)) {
            return response()->json([
                'success' => false,
                'message' => 'Payload pengaturan shift tidak valid.',
            ], 400);
        }

        if (count($rows) > self::MAX_UPDATE_ROWS) {
            return response()->json([
                'success' => false,
                'message' => 'Data pengaturan shift terlalu banyak. Batasi perubahan maksimal ' . number_format(self::MAX_UPDATE_ROWS) . ' sel per proses.',
            ], 422);
        }

        $requestedEmployeeIds = collect($rows)
            ->pluck('employee_id')
            ->filter()
            ->map(fn($employeeId) => (string) $employeeId)
            ->unique()
            ->values()
            ->all();

        if (empty($requestedEmployeeIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada karyawan yang dikirim untuk diperbarui.',
            ], 400);
        }

        $requestedShiftIds = collect($rows)
            ->pluck('shift_id')
            ->filter()
            ->map(fn($shiftId) => (int) $shiftId)
            ->unique()
            ->values()
            ->all();

        try {
            $scopedEmployees = Auth::user()
                ->applyEmployeeScope(
                    Employee::query()
                        ->select('nik')
                        ->whereIn('nik', $requestedEmployeeIds)
                )
                ->get()
                ->keyBy('nik');

            $availableShifts = Shift::query()
                ->whereIn('id', $requestedShiftIds)
                ->where('is_active', true)
                ->get()
                ->keyBy('id');

            DB::transaction(function () use ($rows, $scopedEmployees, $availableShifts, $request) {
                $shiftAssignmentService = app(ShiftAssignmentService::class);

                foreach ($rows as $row) {
                    $employeeId = isset($row['employee_id']) ? (string) $row['employee_id'] : null;
                    $tanggal = $row['tanggal'] ?? null;
                    $shiftId = filled($row['shift_id'] ?? null) ? (int) $row['shift_id'] : null;

                    if (!$employeeId || !$tanggal || !$scopedEmployees->has($employeeId)) {
                        continue;
                    }

                    if ($shiftId !== null && !$availableShifts->has($shiftId)) {
                        continue;
                    }

                    $employee = $scopedEmployees->get($employeeId);

                    $shiftAssignmentService->applyAssignment(
                        $employee,
                        $tanggal,
                        $shiftId,
                        (string) $request->user()->id
                    );
                }
            });
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Update pengaturan shift gagal disimpan.',
            ], 500);
        }

        return response()->json([
            'success' => true,
        ]);
    }
}
