<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Approval\ProcessApprovalRequest;
use App\Http\Requests\KaryawanRequest\StoreEmployeeMovementRequest;
use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\Employee;
use App\Models\EmployeeMovement;
use App\Models\Perusahaan;
use App\Services\Karyawan\EmployeeMovementService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmployeeMovementController extends Controller
{
    public function index(Request $request)
    {
        $service = app(EmployeeMovementService::class);

        abort_unless(
            $service->canAccessMovementModule($request->user()),
            403,
            'Anda tidak memiliki akses ke Transisi Karyawan.'
        );

        $query = EmployeeMovement::query()
            ->with([
                'employee:nik,nama_karyawan,posisi,jabatan,departemen_id,divisi_id',
                'oldDepartemen:id,departemen',
                'newDepartemen:id,departemen',
                'oldDivisi:id,nama_divisi',
                'newDivisi:id,nama_divisi',
                'creator:id,name',
                'hodProcessor:id,name',
                'hrdProcessor:id,name',
                'applier:id,name',
            ])
            ->latest('created_at')
            ->latest('id');

        $service->scopeMovementsVisibleTo($query, $request->user());

        if ($request->filled('movement_type')) {
            $query->where('movement_type', $request->input('movement_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('effective_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('effective_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $like = '%' . $search . '%';

            $query->where(function ($searchQuery) use ($search, $like) {
                $searchQuery->where('employee_nik', 'like', $search . '%')
                    ->orWhere('reference_number', 'like', $like)
                    ->orWhereHas('employee', function ($employeeQuery) use ($like) {
                        $employeeQuery->where('nama_karyawan', 'like', $like);
                    });
            });
        }

        $perPageOptions = [20, 50, 100];
        $perPage = (int) $request->input('per_page', 20);

        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 20;
        }

        return view('admin.employee-movements.index', [
            'movements' => $query->paginate($perPage)->appends($request->query()),
            'typeOptions' => EmployeeMovement::typeOptions(),
            'statusOptions' => EmployeeMovement::statusOptions(),
            'perPageOptions' => $perPageOptions,
            'filters' => array_merge($request->only([
                'movement_type',
                'status',
                'date_from',
                'date_to',
                'search',
                'per_page',
            ]), ['per_page' => $perPage]),
            'canCreateMovement' => $service->canAccessMovementModule($request->user()),
            'canProcessHod' => fn(EmployeeMovement $movement) => $service->canProcessHod($movement, $request->user()),
            'canProcessHrd' => fn(EmployeeMovement $movement) => $service->canProcessHrd($movement, $request->user()),
        ]);
    }

    public function create(Request $request)
    {
        $service = app(EmployeeMovementService::class);

        abort_unless(
            $service->canAccessMovementModule($request->user()),
            403,
            'Anda tidak memiliki akses membuat pengajuan Transisi Karyawan.'
        );

        $selectedEmployee = null;
        $selectedNik = old('employee_nik');

        if (filled($selectedNik)) {
            $selectedEmployee = $this->buildSelectableEmployeeQuery($request)
                ->where('nik', $selectedNik)
                ->first();
        }

        return view('admin.employee-movements.create', [
            'selectedEmployee' => $selectedEmployee,
            'typeOptions' => EmployeeMovement::typeOptions(),
            'departemens' => Departemen::query()
                ->with('perusahaan:id,kode_perusahaan,nama_perusahaan')
                ->orderBy('departemen')
                ->get(['id', 'perusahaan_id', 'departemen']),
            'divisis' => Divisi::query()
                ->orderBy('nama_divisi')
                ->get(['id', 'departemen_id', 'nama_divisi']),
            'areas' => Perusahaan::query()->orderBy('kode_perusahaan')->get(['id', 'kode_perusahaan', 'nama_perusahaan']),
        ]);
    }

    public function store(StoreEmployeeMovementRequest $request, EmployeeMovementService $service)
    {
        try {
            $movement = $service->submit($request->validated(), $request->user());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            toast()->error('Gagal', 'Pengajuan Transisi Karyawan gagal diproses. Periksa data dan coba lagi.');
            return back()->withInput();
        }

        $nextStage = $movement->status === EmployeeMovement::STATUS_PENDING_HRD
            ? 'approval HRD'
            : 'approval HOD';

        toast()->success('Success', $movement->type_label . ' karyawan berhasil diajukan dan menunggu ' . $nextStage . '.');

        return redirect()->route('employee-movements.index');
    }

    public function hodProcess(ProcessApprovalRequest $request, EmployeeMovement $movement, EmployeeMovementService $service)
    {
        abort_unless(
            $service->canProcessHod($movement, $request->user()),
            403,
            'Anda tidak memiliki akses approval HOD untuk pengajuan ini.'
        );

        try {
            $movement = $service->processHod(
                $movement,
                $request->user(),
                (int) $request->validated()['action'],
                $request->validated()['note'] ?? null
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            toast()->error('Gagal', 'Approval HOD gagal diproses. Silakan coba lagi.');
            return back();
        }

        toast()->success('Berhasil', 'Pengajuan ' . strtolower($movement->type_label) . ' telah diproses oleh HOD.');

        return back();
    }

    public function hrdProcess(ProcessApprovalRequest $request, EmployeeMovement $movement, EmployeeMovementService $service)
    {
        abort_unless(
            $service->canProcessHrd($movement, $request->user()),
            403,
            'Anda tidak memiliki akses approval HRD untuk pengajuan ini.'
        );

        try {
            $movement = $service->processHrd(
                $movement,
                $request->user(),
                (int) $request->validated()['action'],
                $request->validated()['note'] ?? null
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            toast()->error('Gagal', 'Approval HRD gagal diproses. Silakan coba lagi.');
            return back();
        }

        if ($movement->status === EmployeeMovement::STATUS_SCHEDULED) {
            $message = 'Pengajuan disetujui HRD dan akan diterapkan otomatis pada tanggal efektif.';
        } elseif ($movement->hrd_status === EmployeeMovement::APPROVAL_APPROVED) {
            $message = 'Pengajuan disetujui HRD dan perubahan sudah diterapkan ke master karyawan.';
        } else {
            $message = 'Pengajuan ditolak HRD.';
        }

        toast()->success('Berhasil', $message);

        return back();
    }

    public function searchEmployees(Request $request)
    {
        $service = app(EmployeeMovementService::class);

        abort_unless(
            $service->canAccessMovementModule($request->user()),
            403,
            'Anda tidak memiliki akses pencarian karyawan untuk Transisi Karyawan.'
        );

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
                    ->orWhere('posisi', 'like', $like)
                    ->orWhere('jabatan', 'like', $like);
            })
            ->orderBy('nama_karyawan')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get();

        return response()->json([
            'results' => $employees
                ->take($perPage)
                ->map(fn(Employee $employee) => $this->formatEmployeeSelectOption($employee))
                ->values(),
            'pagination' => [
                'more' => $employees->count() > $perPage,
            ],
        ]);
    }

    private function buildSelectableEmployeeQuery(Request $request)
    {
        $query = Employee::query()
            ->with([
                'departemen:id,departemen,perusahaan_id',
                'departemen.perusahaan:id,kode_perusahaan,nama_perusahaan',
                'divisi:id,nama_divisi,departemen_id',
            ])
            ->where('status_resign', 'AKTIF')
            ->select([
                'nik',
                'nama_karyawan',
                'area_kerja',
                'departemen_id',
                'divisi_id',
                'posisi',
                'jabatan',
                'status_resign',
            ]);

        return app(EmployeeMovementService::class)->scopeEmployeesForSubmission($query, $request->user());
    }

    private function formatEmployeeSelectOption(Employee $employee): array
    {
        $companyCode = optional(optional($employee->departemen)->perusahaan)->kode_perusahaan ?: $employee->area_kerja;
        $departmentName = optional($employee->departemen)->departemen;
        $divisionName = optional($employee->divisi)->nama_divisi;
        $details = collect([$companyCode, $departmentName, $divisionName, $employee->posisi ?: null])
            ->filter()
            ->implode(' | ');

        return [
            'id' => $employee->nik,
            'text' => trim($employee->nama_karyawan . ' - ' . $employee->nik . ($details ? ' | ' . $details : '')),
            'employee' => [
                'nik' => $employee->nik,
                'name' => $employee->nama_karyawan,
                'area' => $companyCode,
                'posisi' => $employee->posisi,
                'jabatan' => $employee->jabatan,
                'departemen_id' => $employee->departemen_id,
                'departemen' => $departmentName,
                'divisi_id' => $employee->divisi_id,
                'divisi' => $divisionName,
            ],
        ];
    }
}
