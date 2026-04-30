<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Overtime\OvertimeCalculationRequest;
use App\Http\Requests\Overtime\OvertimePayRuleRequest;
use App\Models\Employee;
use App\Models\OvertimePayRule;
use App\Services\Overtime\OvertimePayCalculatorService;

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
        $result = $calculator->calculate(
            $scheduleType,
            $validated['day_type'],
            (int) round(((float) $validated['overtime_hours']) * 60),
            (float) $validated['monthly_wage']
        );
        $result['employee'] = $selectedEmployee ? [
            'nik' => $selectedEmployee->nik,
            'name' => $selectedEmployee->nama_karyawan,
            'work_pattern' => optional($selectedEmployee->workPattern)->code,
        ] : null;

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
                'workPattern:id,code,name,pattern_basis,weekly_work_days,work_duration_value,off_duration_value',
                'departemen.perusahaan:id,kode_perusahaan,nama_perusahaan',
                'divisi:id,nama_divisi,departemen_id',
            ])
            ->where('nik', $nik)
            ->first();
    }
}
