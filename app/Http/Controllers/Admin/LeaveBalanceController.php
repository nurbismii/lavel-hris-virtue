<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\LeaveBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LeaveBalance\StoreLeaveBalanceEntryRequest;
use App\Models\Employee;
use App\Models\LeaveBalanceLedger;
use App\Services\LeaveBalance\LeaveBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class LeaveBalanceController extends Controller
{
    private const ACTIVE_STATUS = 'AKTIF';
    private const ALLOWED_AREAS = ['VDNI', 'VDNIP'];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $employees = $request->user()
            ->applyEmployeeScope(
                Employee::query()
                    ->select(['nik', 'nama_karyawan', 'departemen_id', 'divisi_id', 'area_kerja', 'status_resign', 'sisa_cuti'])
                    ->with(['departemen:id,departemen', 'divisi:id,nama_divisi'])
            )
            ->where('status_resign', self::ACTIVE_STATUS)
            ->whereIn('area_kerja', self::ALLOWED_AREAS)
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('nik', 'like', $search . '%')
                        ->orWhere('nama_karyawan', 'like', $search . '%');
                });
            })
            ->orderBy('nama_karyawan')
            ->paginate(50)
            ->withQueryString();

        return view('admin.leave-balances.index', [
            'employees' => $employees,
            'filters' => $filters,
            'isTableReady' => Schema::hasTable('leave_balance_ledgers'),
        ]);
    }

    public function show(Request $request, Employee $employee, LeaveBalanceService $leaveBalanceService)
    {
        $employee = $request->user()
            ->applyEmployeeScope(
                Employee::query()
                    ->select(['nik', 'nama_karyawan', 'departemen_id', 'divisi_id', 'area_kerja', 'status_resign', 'sisa_cuti'])
                    ->with(['departemen:id,departemen', 'divisi:id,nama_divisi'])
            )
            ->where('status_resign', self::ACTIVE_STATUS)
            ->whereIn('area_kerja', self::ALLOWED_AREAS)
            ->where('nik', $employee->nik)
            ->firstOrFail();

        $ledgers = Schema::hasTable('leave_balance_ledgers')
            ? LeaveBalanceLedger::query()
                ->with('actor:id,name')
                ->where('employee_nik', $employee->nik)
                ->latest('transaction_date')
                ->latest('id')
                ->paginate(50)
                ->withQueryString()
            : null;

        return view('admin.leave-balances.show', [
            'employee' => $employee,
            'currentBalance' => $leaveBalanceService->currentBalance($employee),
            'isTableReady' => Schema::hasTable('leave_balance_ledgers'),
            'ledgers' => $ledgers,
        ]);
    }

    public function store(
        StoreLeaveBalanceEntryRequest $request,
        Employee $employee,
        LeaveBalanceService $leaveBalanceService
    ) {
        $employee = $request->user()
            ->applyEmployeeScope(Employee::query())
            ->where('status_resign', self::ACTIVE_STATUS)
            ->whereIn('area_kerja', self::ALLOWED_AREAS)
            ->where('nik', $employee->nik)
            ->firstOrFail();

        try {
            $leaveBalanceService->recordManualEntry($employee, $request->validated(), $request->user());
        } catch (LeaveBalanceException $exception) {
            toast()->warning('Peringatan', $exception->getMessage());
            return back()->withInput();
        }

        toast()->success('Berhasil', 'Transaksi saldo cuti berhasil dicatat.');
        return redirect()->route('leave-balances.show', $employee->nik);
    }
}
