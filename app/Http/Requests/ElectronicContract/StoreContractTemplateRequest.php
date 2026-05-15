<?php

namespace App\Http\Requests\ElectronicContract;

use App\Models\ContractTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']);
    }

    public function rules(): array
    {
        return [
            'contract_type' => ['required', Rule::in(array_keys(ContractTemplate::typeOptions()))],
            'name' => 'required|string|max:150',
            'letterhead_html' => 'nullable|string|max:200000',
            'body_html' => 'required|string|max:500000',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'contract_type.required' => 'Tipe kontrak wajib dipilih.',
            'body_html.required' => 'Isi template kontrak wajib diisi.',
            'name.required' => 'Nama template wajib diisi.',
        ];
    }
}
