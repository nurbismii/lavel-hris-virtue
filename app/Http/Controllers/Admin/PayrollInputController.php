<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\SavePayrollInputRequest;
use App\Models\Perusahaan;
use App\Services\Payroll\PayrollInputs;
use App\Services\Salary\EmployeeSalaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PayrollInputController extends Controller
{
    public function index(Request $request, PayrollInputs $inputs)
    {
        $this->ensureReady();
        $scope = $request->validate(PayrollInputs::periodRules() + ['q'=>['nullable','string','max:100']]);
        [$employer, $period] = [$scope['employer_id'], $scope['period']];
        $search = trim($scope['q'] ?? '');
        $employees = $inputs->employees($employer, $period)
            ->when($search !== '', fn ($q) => $q->where(fn ($match) => $match->where('nik', $search)->orWhere('nama_karyawan', 'like', $search.'%')))
            ->orderBy('nik')->paginate(40, ['nik','nama_karyawan'])->withQueryString();
        $records = $inputs->records($employer, $period, $employees->pluck('nik')->all());
        return response()->view('admin.payroll.inputs', [
            'company'=>Perusahaan::findOrFail($employer), 'period'=>$period, 'cutoff'=>$inputs->cutoff($period),
            'employees'=>$employees, 'records'=>$records, 'inputs'=>$inputs, 'search'=>$search,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function edit(Request $request, string $nik, PayrollInputs $inputs, EmployeeSalaryService $salaries)
    {
        $this->ensureReady();
        $scope = $request->validate(PayrollInputs::periodRules());
        [$employer, $period] = [$scope['employer_id'], $scope['period']];
        $employee = $inputs->employees($employer, $period)->whereKey($nik)->firstOrFail();
        $record = $inputs->forEmployee($inputs->records($employer, $period, [$nik])->get($nik, collect()), $period);
        $salary = $salaries->forDate($nik, $period.'-01');
        $fixedParts = null;
        $fixedError = null;
        if ($salary && $employee->entry_date) {
            try { $fixedParts = $salaries->payrollFixedAllowance($salary, $employee, $period); }
            catch (\Illuminate\Validation\ValidationException $exception) { $fixedError = collect($exception->errors())->flatten()->first(); }
        }
        return response()->view('admin.payroll.input-edit', [
            'company'=>Perusahaan::findOrFail($employer), 'period'=>$period, 'employee'=>$employee,
            'record'=>$record, 'salary'=>$salary, 'orders'=>$inputs->orders($nik, $period)->orderBy('overtime_date')->orderBy('id')->get(),
            'customComponents'=>\App\Models\SalaryComponent::orderBy('code')->get(),
            'fixedParts'=>$fixedParts, 'fixedError'=>$fixedError,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function save(SavePayrollInputRequest $request, string $nik, PayrollInputs $inputs)
    {
        $this->ensureReady();
        $data = $request->validated();
        $inputs->save($data['employer_id'], $data['period'], $nik, $data, (string) $request->user()->id);
        return response()->json(['success'=>true, 'message'=>'Data payroll karyawan disimpan.', 'data'=>[
            'redirect_url'=>route('payroll.inputs', ['employer_id'=>$data['employer_id'], 'period'=>$data['period']]),
        ]]);
    }

    private function ensureReady(): void
    {
        abort_unless(Schema::hasTable('payroll_inputs') && Schema::hasTable('payroll_entries') && Schema::hasTable('salary_components'), 503, 'Penyimpanan isian payroll belum disiapkan.');
    }
}
