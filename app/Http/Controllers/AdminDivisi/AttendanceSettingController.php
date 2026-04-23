<?php

namespace App\Http\Controllers\AdminDivisi;

use App\Http\Controllers\Concerns\ValidatesZipUploads;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\EmployeeAttendanceSetting;
use App\Models\NationalHoliday;
use App\Jobs\ProcessEmployeeMediaZipUpload;
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
        $nationalHolidays = collect();
        $nationalHolidaysByDate = collect();
        $canManageNationalHolidays = $user->hasRole(['Super Admin', 'HR']);
        $isNationalHolidayTableReady = Schema::hasTable('national_holidays');

        if ($isNationalHolidayTableReady) {
            $nationalHolidays = NationalHoliday::query()
                ->whereBetween('holiday_date', [
                    $start->copy()->startOfYear()->toDateString(),
                    $end->copy()->endOfYear()->toDateString(),
                ])
                ->orderBy('holiday_date')
                ->get();

            $nationalHolidaysByDate = $nationalHolidays->keyBy(function ($holiday) {
                return $holiday->holiday_date->toDateString();
            });
        }

        if ($selectedDepartemenId) {
            $employees = Employee::with(['divisi', 'departemen', 'workPattern'])
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
            'nationalHolidays',
            'nationalHolidaysByDate',
            'canManageNationalHolidays',
            'isNationalHolidayTableReady'
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

        $scopedEmployees = Auth::user()
            ->applyEmployeeScope(Employee::query()->with('workPattern'))
            ->get()
            ->keyBy('nik');

        $allowedEmployeeIds = $scopedEmployees
            ->keys()
            ->all();

        try {
            DB::transaction(function () use ($rows, $allowedEmployeeIds, $scopedEmployees) {
                $scheduleService = app(WorkScheduleService::class);

                foreach ($rows as $row) {
                    $employeeId = isset($row['employee_id']) ? (string) $row['employee_id'] : null;
                    $tanggal = $row['tanggal'] ?? null;

                    if (!$employeeId || !$tanggal || !in_array($employeeId, $allowedEmployeeIds, true)) {
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

        $filePath = $request->file('face_reference_zip')->store(
            'employee-zip-imports',
            config('filesystems.employee_import_disk', config('filesystems.default'))
        );
        ProcessEmployeeMediaZipUpload::dispatch($filePath, 'face_reference', $request->user()->id);

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

    public function storeNationalHoliday(Request $request)
    {
        if (!Schema::hasTable('national_holidays')) {
            toast()->error('Error', 'Tabel tanggal merah nasional belum tersedia. Jalankan migrate terlebih dahulu.');
            return redirect()->route('set-kehadiran.index', $request->only(['periode', 'departemen', 'divisi']));
        }

        $validated = $request->validate([
            'holiday_date' => 'required|date',
            'holiday_name' => 'required|string|max:150',
        ]);

        $holiday = NationalHoliday::firstOrNew([
            'holiday_date' => $validated['holiday_date'],
        ]);

        $isNewRecord = !$holiday->exists;
        $holiday->holiday_name = $validated['holiday_name'];
        $holiday->updated_by = $request->user()->id;

        if ($isNewRecord) {
            $holiday->created_by = $request->user()->id;
        }

        $holiday->save();

        toast()->success(
            'Success',
            $isNewRecord
                ? 'Tanggal merah nasional berhasil ditambahkan.'
                : 'Tanggal merah nasional berhasil diperbarui.'
        );

        return redirect()->route('set-kehadiran.index', $request->only(['periode', 'departemen', 'divisi']));
    }

    public function destroyNationalHoliday(Request $request, NationalHoliday $nationalHoliday)
    {
        $nationalHoliday->delete();

        toast()->success('Success', 'Tanggal merah nasional berhasil dihapus.');

        return redirect()->route('set-kehadiran.index', $request->only(['periode', 'departemen', 'divisi']));
    }

}
