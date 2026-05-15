<?php

namespace App\Http\Requests\ElectronicContract;

use App\Models\ContractClause;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractClauseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']);
    }

    public function rules(): array
    {
        $ignoreId = optional($this->route('clause'))->id;

        return [
            'clause_key' => [
                'required',
                Rule::in(array_keys(ContractClause::keyOptions())),
                Rule::unique('contract_clauses', 'clause_key')->ignore($ignoreId),
            ],
            'name' => 'required|string|max:150',
            'body_html' => 'required|string|max:300000',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'clause_key.required' => 'Jenis klausul wajib dipilih.',
            'clause_key.unique' => 'Jenis klausul ini sudah dibuat.',
            'body_html.required' => 'Isi klausul wajib diisi.',
        ];
    }
}
