<?php

namespace App\Http\Controllers\AdminDivisi;

use App\Http\Controllers\Concerns\ValidatesZipUploads;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\EmployeeAttendanceSetting;
use App\Models\ImportHistory;
use App\Models\NationalHoliday;
use App\Jobs\ProcessEmployeeMediaZipUpload;
use App\Services\ImportHistory\ImportHistoryService;
use App\Services\Presensi\WorkScheduleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\Departemen;
use App\Models\Divisi;
use RuntimeException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendanceSettingController extends Controller
{
    use ValidatesZipUploads;

    private const MATRIX_EMPLOYEE_LIMIT = 500;

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

        $requiresDivisionFilter = $selectedDepartemenId && !$selectedDivisiId && $divisis->count() > 1;
        $employeeLimitExceeded = false;
        $matrixEmployeeLimit = self::MATRIX_EMPLOYEE_LIMIT;
        $employees = collect();
        $offData = collect();
        $nationalHolidaysByDate = collect();
        $isNationalHolidayTableReady = Schema::hasTable('national_holidays');

        if ($isNationalHolidayTableReady) {
            $nationalHolidaysByDate = NationalHoliday::query()
                ->whereBetween('holiday_date', [
                    $start->copy()->startOfYear()->toDateString(),
                    $end->copy()->endOfYear()->toDateString(),
                ])
                ->orderBy('holiday_date')
                ->get()
                ->keyBy(function ($holiday) {
                    return $holiday->holiday_date->toDateString();
                });
        }

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

        $scheduleMap = app(WorkScheduleService::class)->buildScheduleMap(
            $employees,
            $offData->flatten(1),
            $start,
            $end
        );

        return view('admin-divisi.set-kehadiran.index', compact(
            'employees',
            'dates',
            'offData',
            'scheduleMap',
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
            'isDivisionReadonly',
            'nationalHolidaysByDate',
            'requiresDivisionFilter',
            'employeeLimitExceeded',
            'matrixEmployeeLimit'
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
                'message' => 'Tidak ada karyawan yang dikirim untuk diperbarui.'
            ], 400);
        }

        try {
            $scopedEmployees = Auth::user()
                ->applyEmployeeScope(
                    Employee::query()
                        ->select('nik', 'work_pattern_id', 'work_pattern_start_date')
                        ->whereIn('nik', $requestedEmployeeIds)
                        ->with('workPattern')
                )
                ->get()
                ->keyBy('nik');

            DB::transaction(function () use ($rows, $scopedEmployees) {
                $scheduleService = app(WorkScheduleService::class);

                foreach ($rows as $row) {
                    $employeeId = isset($row['employee_id']) ? (string) $row['employee_id'] : null;
                    $tanggal = $row['tanggal'] ?? null;

                    if (!$employeeId || !$tanggal || !$scopedEmployees->has($employeeId)) {
                        continue;
                    }

                    $status = strtoupper((string) ($row['status'] ?? ''));

                    if (!in_array($status, [
                        EmployeeAttendanceSetting::STATUS_OFF,
                        EmployeeAttendanceSetting::STATUS_HADIR,
                    ], true)) {
                        continue;
                    }

                    $employee = $scopedEmployees->get($employeeId);

                    if (!$employee) {
                        continue;
                    }

                    $scheduleService->applyManualOverride($employee, $tanggal, $status);
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
                'message' => 'Update setting hari off gagal disimpan.',
            ], 500);
        }

        return response()->json([
            'success' => true
        ]);
    }

    public function bulkUploadFaceReferences(Request $request)
    {
        $this->validateZipUploads($request, [
            'face_reference_zip' => [
                'label' => 'ZIP foto referensi',
                'required' => true,
            ],
        ]);

        $uploadedFile = $request->file('face_reference_zip');
        $disk = config('filesystems.employee_import_disk', config('filesystems.default'));
        $filePath = $uploadedFile->store(
            'employee-zip-imports',
            $disk
        );
        $history = app(ImportHistoryService::class)->createQueued([
            'import_type' => ImportHistory::TYPE_FACE_REFERENCE,
            'module' => 'attendance_setting',
            'source' => ImportHistory::SOURCE_ZIP,
            'file_name' => $uploadedFile->getClientOriginalName(),
            'file_path' => $filePath,
            'disk' => $disk,
            'mime_type' => $uploadedFile->getClientMimeType(),
            'file_size' => $uploadedFile->getSize(),
            'created_by' => (string) $request->user()->id,
            'summary' => [
                'media_type' => 'face_reference',
            ],
        ]);

        ProcessEmployeeMediaZipUpload::dispatch(
            $filePath,
            'face_reference',
            $request->user()->id,
            0,
            optional($history)->import_id,
            optional($history)->id
        );

        $redirectUrl = route('set-kehadiran.index', $request->only(['periode', 'departemen', 'divisi']));
        $message = 'ZIP foto referensi sedang diproses di background. Cek notifikasi untuk hasil akhirnya.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'redirect_url' => $redirectUrl,
            ]);
        }

        toast()->success('Success', $message);
        return redirect()->to($redirectUrl);
    }

}
