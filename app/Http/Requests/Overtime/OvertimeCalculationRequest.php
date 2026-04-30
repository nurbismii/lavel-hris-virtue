<?php

namespace App\Http\Requests\Overtime;

use App\Models\OvertimePayRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OvertimeCalculationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'nik_karyawan' => ['nullable', 'string', 'exists:employees,nik'],
            'schedule_type' => ['required', Rule::in(array_keys(OvertimePayRule::scheduleTypeOptions()))],
            'day_type' => ['required', Rule::in(array_keys(OvertimePayRule::dayTypeOptions()))],
            'monthly_wage' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'overtime_hours' => ['required', 'numeric', 'min:0.01', 'max:24'],
        ];
    }

    public function messages(): array
    {
        return [
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
