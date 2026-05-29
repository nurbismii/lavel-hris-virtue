<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OvertimeOrder;
use App\Models\Presensi;
use App\Notifications\StatusPengajuanNotification;
use App\Services\Presensi\AttendancePeriodLockService;
use App\Services\Presensi\OvertimeOrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OvertimeOrderController extends Controller
{
    private const EMPLOYEE_AREA_CODES = ['VDNI', 'VDNIP'];

    public function index(Request $request)
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

        $query = $request->user()
            ->applyEmployeeRelationScope(
                OvertimeOrder::query()->with(['employee.divisi.departemen', 'requester'])
            )
            ->latest('overtime_date')
            ->latest('id');

        if ($request->filled('response_status')) {
            $query->where('employee_response_status', $request->response_status);
        }

        return view('admin.overtime-orders.index', [
            'overtimeOrders' => $query->paginate(50)->appends($request->query()),
            'responseOptions' => $this->responseOptions(),
        ]);
    }

    public function create(Request $request)
    {
        $selectedEmployee = null;
        $selectedNik = old('nik_karyawan');

        if (filled($selectedNik)) {
            $selectedEmployee = $this->buildSelectableEmployeeQuery($request)
                ->where('nik', $selectedNik)
                ->first();
        }

        return view('admin.overtime-orders.create', [
            'selectedEmployee' => $selectedEmployee,
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function searchEmployees(Request $request)
    {
        $term = trim((string) $request->input('q', ''));
        $page = max((int) $request->input('page', 1), 1);
        $perPage = 20;

        if (strlen($term) < 2) {
            return response()->json([
                'results' => [],
                'pagination' => ['more' => false],
            ]);
        }

        $employees = $this->buildSelectableEmployeeQuery($request)
            ->where(function ($query) use ($term) {
                $like = '%' . $term . '%';

                $query->where('nik', 'like', $like)
                    ->orWhere('nama_karyawan', 'like', $like)
                    ->orWhereHas('departemen', function ($departemenQuery) use ($like) {
                        $departemenQuery->where('departemen', 'like', $like);
                    })
                    ->orWhereHas('divisi', function ($divisiQuery) use ($like) {
                        $divisiQuery->where('nama_divisi', 'like', $like);
                    });
            })
            ->orderBy('nama_karyawan')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get();

        $hasMore = $employees->count() > $perPage;

        return response()->json([
            'results' => $employees
                ->take($perPage)
                ->map(fn(Employee $employee) => $this->formatEmployeeSelectOption($employee))
                ->values(),
            'pagination' => [
                'more' => $hasMore,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);

        $periodLockMessage = app(AttendancePeriodLockService::class)->guardDate(
            $validated['overtime_date'],
            'Perintah lembur'
        );

        if ($periodLockMessage) {
            toast()->warning('Peringatan', $periodLockMessage);
            return back()->withInput();
        }

        $employee = $this->buildSelectableEmployeeQuery($request)
            ->where('nik', $validated['nik_karyawan'])
            ->first();

        if (!$employee) {
            throw ValidationException::withMessages([
                'nik_karyawan' => 'Karyawan tidak tersedia dalam scope departemen/divisi Anda atau bukan karyawan aktif VDNI/VDNIP.',
            ]);
        }

        $validated['requested_by_user_id'] = $request->user()->id;
        $validated['required_minutes'] = $this->calculateRequiredMinutes(
            $validated['start_time'] ?? null,
            $validated['end_time'] ?? null
        );

        $overtimeOrder = OvertimeOrder::create($validated);
        $employeeUser = $overtimeOrder->employeeUser;

        if ($employeeUser) {
            $employeeUser->notify(new StatusPengajuanNotification([
                'judul' => 'Perintah Lembur',
                'pesan' => 'Anda menerima perintah ' . strtolower($overtimeOrder->type_label) . ' untuk tanggal ' . $overtimeOrder->overtime_date->format('d-m-Y') . '.',
                'url' => route('lembur.index'),
                'tipe' => $overtimeOrder->type_label,
            ]));
        }

        toast()->success('Success', 'Perintah lembur berhasil dibuat.');
        return redirect()->route('overtime-orders.index');
    }

    public function show(Request $request, $id)
    {
        $overtimeOrder = $request->user()
            ->applyEmployeeRelationScope(
                OvertimeOrder::query()->with(['employee.divisi.departemen', 'requester', 'employeeUser'])
            )
            ->findOrFail($id);

        $attendanceRecord = Presensi::query()
            ->where('nik_karyawan', $overtimeOrder->nik_karyawan)
            ->whereDate('tanggal', $overtimeOrder->overtime_date)
            ->first();

        return view('admin.overtime-orders.show', [
            'overtimeOrder' => $overtimeOrder,
            'attendanceRecord' => $attendanceRecord,
            'attendanceOutcome' => app(OvertimeOrderService::class)->evaluateAttendance($overtimeOrder, $attendanceRecord),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $overtimeOrder = $request->user()
            ->applyEmployeeRelationScope(OvertimeOrder::query()->with('employeeUser'))
            ->findOrFail($id);

        if ($overtimeOrder->employee_response_status !== OvertimeOrder::RESPONSE_PENDING) {
            toast()->warning('Peringatan', 'Perintah lembur yang sudah direspons tidak dapat dihapus.');
            return redirect()->route('overtime-orders.index');
        }

        $periodLockMessage = app(AttendancePeriodLockService::class)->guardDate(
            $overtimeOrder->overtime_date,
            'Pembatalan perintah lembur'
        );

        if ($periodLockMessage) {
            toast()->warning('Peringatan', $periodLockMessage);
            return redirect()->route('overtime-orders.index');
        }

        if ($overtimeOrder->employeeUser) {
            $overtimeOrder->employeeUser->notify(new StatusPengajuanNotification([
                'judul' => 'Perintah Lembur',
                'pesan' => 'Perintah lembur untuk tanggal ' . $overtimeOrder->overtime_date->format('d-m-Y') . ' telah dibatalkan.',
                'url' => route('lembur.index'),
                'tipe' => 'Perintah Lembur',
            ]));
        }

        $overtimeOrder->delete();

        toast()->success('Success', 'Perintah lembur berhasil dibatalkan.');
        return redirect()->route('overtime-orders.index');
    }

    private function validateRequest(Request $request): array
    {
        $validated = $request->validate([
            'nik_karyawan' => 'required|string|exists:employees,nik',
            'overtime_type' => ['required', Rule::in(array_keys($this->typeOptions()))],
            'overtime_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'reason' => 'required|string|max:2000',
            'instruction_notes' => 'nullable|string|max:2000',
        ]);

        if ($validated['start_time'] === $validated['end_time']) {
            throw ValidationException::withMessages([
                'end_time' => 'Jam selesai lembur tidak boleh sama dengan jam mulai.',
            ]);
        }

        return $validated;
    }

    private function calculateRequiredMinutes(?string $startTime, ?string $endTime): ?int
    {
        if (!$startTime || !$endTime) {
            return null;
        }

        $start = strtotime($startTime);
        $end = strtotime($endTime);

        if ($end <= $start) {
            $end += 24 * 60 * 60;
        }

        return (int) round(($end - $start) / 60);
    }

    private function typeOptions(): array
    {
        return [
            OvertimeOrder::TYPE_OFF => 'Lembur Off',
            OvertimeOrder::TYPE_HOLIDAY => 'Lembur Tanggal Merah',
            OvertimeOrder::TYPE_EXTRA_HOURS => 'Lembur Kelebihan Jam',
        ];
    }

    private function responseOptions(): array
    {
        return [
            OvertimeOrder::RESPONSE_PENDING => 'Menunggu Respons',
            OvertimeOrder::RESPONSE_ACCEPTED => 'Disetujui Karyawan',
            OvertimeOrder::RESPONSE_REJECTED => 'Ditolak Karyawan',
        ];
    }

    private function buildSelectableEmployeeQuery(Request $request)
    {
        $query = Employee::query()
            ->with([
                'workPattern:id,code',
                'departemen:id,departemen,perusahaan_id',
                'departemen.perusahaan:id,kode_perusahaan,nama_perusahaan',
                'divisi:id,nama_divisi,departemen_id',
            ])
            ->where('status_resign', 'AKTIF')
            ->where(function ($employeeQuery) {
                $employeeQuery
                    ->whereIn('area_kerja', self::EMPLOYEE_AREA_CODES)
                    ->orWhereHas('departemen.perusahaan', function ($companyQuery) {
                        $companyQuery->whereIn('kode_perusahaan', self::EMPLOYEE_AREA_CODES);
                    });
            });

        return $request->user()->applyEmployeeScope($query);
    }

    private function formatEmployeeSelectOption(Employee $employee): array
    {
        $companyCode = optional(optional($employee->departemen)->perusahaan)->kode_perusahaan;
        $departmentName = optional($employee->departemen)->departemen;
        $divisionName = optional($employee->divisi)->nama_divisi;
        $workPatternCode = optional($employee->workPattern)->code;
        $details = collect([$companyCode, $departmentName, $divisionName, $workPatternCode ? 'Pola ' . $workPatternCode : null])
            ->filter()
            ->implode(' | ');

        return [
            'id' => $employee->nik,
            'text' => trim($employee->nama_karyawan . ' - ' . $employee->nik . ($details ? ' | ' . $details : '')),
        ];
    }
}
