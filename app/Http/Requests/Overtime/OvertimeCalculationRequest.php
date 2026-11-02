<?php

namespace App\Http\Requests\Overtime;

use App\Models\OvertimePayRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OvertimeCalculationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']);
    }

    public function rules(): array
    {
        $history = $this->input('wage_source') === 'history';
        return [
            'wage_source' => ['nullable', Rule::in(['history', 'manual'])],
            'nik_karyawan' => [$history ? 'required' : 'nullable', 'string', 'exists:employees,nik'],
            'overtime_date' => [$history ? 'required' : 'nullable', 'date_format:Y-m-d'],
            'schedule_type' => ['required', Rule::in(array_keys(OvertimePayRule::scheduleTypeOptions()))],
            'day_type' => ['required', Rule::in(array_keys(OvertimePayRule::dayTypeOptions()))],
            'monthly_wage' => [$history ? 'nullable' : 'required_without:basic_salary', 'nullable', 'numeric', 'min:0', 'max:999999999'],
            'basic_salary' => [$history ? 'nullable' : 'required_without:monthly_wage', 'nullable', 'numeric', 'min:0.01', 'max:999999999'],
            'fixed_allowance' => [$history ? 'nullable' : 'required_with:basic_salary', 'nullable', 'numeric', 'min:0', 'max:999999999'],
            'non_fixed_allowance' => [$history ? 'nullable' : 'required_with:basic_salary', 'nullable', 'numeric', 'min:0', 'max:999999999'],
            'overtime_hours' => ['required', 'numeric', 'min:0.01', 'max:24'],
        ];
    }

    public function messages(): array
    {
        return [
            'nik_karyawan.required' => 'Pilih karyawan untuk membaca riwayat upah.',
            'overtime_date.required' => 'Pilih tanggal pelaksanaan lembur.',
            'basic_salary.required_without' => 'Isi gaji pokok untuk simulasi manual.',
            'fixed_allowance.required_with' => 'Isi tunjangan tetap, atau 0 jika tidak ada.',
            'non_fixed_allowance.required_with' => 'Isi tunjangan tidak tetap, atau 0 jika tidak ada.',
            'schedule_type.required' => 'Pilih pola jadwal kerja.',
            'day_type.required' => 'Pilih jenis hari lembur.',
            'monthly_wage.required' => 'Isi dasar upah lembur per bulan.',
            'monthly_wage.numeric' => 'Dasar upah lembur harus berupa angka.',
            'overtime_hours.required' => 'Isi durasi lembur dalam jam.',
            'overtime_hours.numeric' => 'Durasi lembur harus berupa angka.',
            'overtime_hours.min' => 'Durasi lembur minimal 0,01 jam.',
        ];
    }
}
