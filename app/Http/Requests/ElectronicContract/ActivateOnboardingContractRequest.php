<?php

namespace App\Http\Requests\ElectronicContract;

use Illuminate\Foundation\Http\FormRequest;

class ActivateOnboardingContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']);
    }

    public function rules(): array
    {
        return [
            'employee_nik' => 'required|string|max:100|exists:employees,nik',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_nik.required' => 'NIK employee HRIS wajib diisi.',
            'employee_nik.exists' => 'NIK employee HRIS tidak ditemukan di master karyawan.',
        ];
    }
}
