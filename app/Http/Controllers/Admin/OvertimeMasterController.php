<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Overtime\OvertimeCalculationRequest;
use App\Http\Requests\Overtime\OvertimePayRuleRequest;
use App\Models\Employee;
use App\Models\OvertimePayRule;
use App\Services\Overtime\OvertimePayCalculatorService;
use App\Services\Salary\EmployeeSalaryService;
use Illuminate\Validation\ValidationException;

class OvertimeMasterController extends Controller
{
    public function index()
    {
        return view('admin.overtime-masters.index', $this->viewData());
    }

    public function create()
    {
        return view('admin.overtime-masters.create', $this->formData(new OvertimePayRule()));
    }

    public function store(OvertimePayRuleRequest $request)
    {
        OvertimePayRule::create($request->payload());

        toast()->success('Success', 'Master rule lembur berhasil ditambahkan.');

        return redirect()->route('overtime-masters.index');
    }

    public function edit(OvertimePayRule $overtimePayRule)
    {
        return view('admin.overtime-masters.edit', $this->formData($overtimePayRule));
    }

    public function update(OvertimePayRuleRequest $request, OvertimePayRule $overtimePayRule)
    {
        $overtimePayRule->update($request->payload());

        toast()->success('Success', 'Master rule lembur berhasil diperbarui.');

        return redirect()->route('overtime-masters.index');
    }

    public function calculate(OvertimeCalculationRequest $request, OvertimePayCalculatorService $calculator)
    {
        $validated = $request->validated();
        $selectedEmployee = $this->selectedEmployee($validated['nik_karyawan'] ?? null);
        $scheduleType = $selectedEmployee
            ? $calculator->resolveScheduleTypeFromWorkPattern($selectedEmployee->workPattern)
            : $validated['schedule_type'];
        $salary = null;
        $salaryService = app(EmployeeSalaryService::class);
        if (($validated['wage_source'] ?? 'manual') === 'history') {
            abort_unless($request->user()->hasMenuAccess('slip_gaji_admin'), 403);
            $salary = $salaryService->requireForDate($selectedEmployee->nik, $validated['overtime_date']);
            $monthlyWage = $salaryService->overtimeBasis($salary);
        } elseif (isset($validated['basic_salary'])) {
            $monthlyWage = $salaryService->basis((float) $validated['basic_salary'],
                (float) $validated['fixed_allowance'], (float) $validated['non_fixed_allowance']);
        } else {
            $monthlyWage = (float) $validated['monthly_wage'];
        }
        if ($validated['day_type'] === OvertimePayRule::DAY_SHORTEST_WORKDAY_HOLIDAY && $scheduleType !== OvertimePayRule::SCHEDULE_SIX_ONE) {
            throw ValidationException::withMessages(['day_type' => 'Hari libur pada hari kerja terpendek hanya berlaku untuk pola 6:1.']);
        }
        $result = $calculator->calculate(
            $scheduleType,
            $validated['day_type'],
            (int) round(((float) $validated['overtime_hours']) * 60),
            $monthlyWage
        );
        $result['salary_history'] = $salary;
        $result['overtime_date'] = $validated['overtime_date'] ?? null;
        $result['employee'] = $selectedEmployee ? [
            'nik' => $selectedEmployee->nik,
            'name' => $selectedEmployee->nama_karyawan,
            'work_pattern' => optional($selectedEmployee->workPattern)->code,
        ] : null;

        toast()->success('Perhitungan selesai', 'Rincian estimasi upah lembur telah ditampilkan.');
        return view('admin.overtime-masters.index', $this->viewData($result, $selectedEmployee));
    }

    private function formData(OvertimePayRule $rule): array
    {
        return [
            'rule' => $rule,
            'ruleScheduleTypeOptions' => OvertimePayRule::ruleScheduleTypeOptions(),
            'dayTypeOptions' => OvertimePayRule::dayTypeOptions(),
        ];
    }

    private function viewData(?array $calculationResult = null, ?Employee $selectedEmployee = null): array
    {
        $selectedEmployee = $selectedEmployee ?: $this->selectedEmployee(old('nik_karyawan'));
        return [
            'rules' => OvertimePayRule::query()
                ->orderBy('sort_order')
                ->get(),
            'scheduleTypeOptions' => OvertimePayRule::scheduleTypeOptions(),
            'ruleScheduleTypeOptions' => OvertimePayRule::ruleScheduleTypeOptions(),
            'dayTypeOptions' => OvertimePayRule::dayTypeOptions(),
            'calculationResult' => $calculationResult,
            'selectedEmployee' => $selectedEmployee,
        ];
    }

    private function selectedEmployee(?string $nik): ?Employee
    {
        if (blank($nik)) {
            return null;
        }

        return Employee::query()
            ->with([
                'workPattern',
                'departemen.perusahaan:id,kode_perusahaan,nama_perusahaan',
                'divisi:id,nama_divisi,departemen_id',
            ])
            ->where('nik', $nik)
            ->first();
    }
}
