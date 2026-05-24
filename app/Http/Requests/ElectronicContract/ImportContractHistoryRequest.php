<?php

namespace App\Http\Requests\ElectronicContract;

use Illuminate\Foundation\Http\FormRequest;

class ImportContractHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            && $this->user()->hasRole(['Super Admin', 'HR'])
            && $this->user()->hasMenuAccess('contract_renewal');
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx,xls|max:20480',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File history kontrak wajib dipilih.',
            'file.mimes' => 'File history kontrak harus berformat XLSX atau XLS.',
            'file.max' => 'Ukuran file history kontrak maksimal 20MB.',
        ];
    }
}
