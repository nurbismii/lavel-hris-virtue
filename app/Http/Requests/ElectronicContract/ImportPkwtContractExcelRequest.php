<?php

namespace App\Http\Requests\ElectronicContract;

use App\Models\EmployeeContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportPkwtContractExcelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']);
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
            'signing_method' => ['required', Rule::in(array_keys(EmployeeContract::signingMethodOptions()))],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File Excel PKWT 1 wajib dipilih.',
            'file.mimes' => 'File import harus berformat XLSX atau XLS.',
            'file.max' => 'Ukuran file import maksimal 10MB.',
            'signing_method.required' => 'Metode tanda tangan wajib dipilih.',
        ];
    }
}
