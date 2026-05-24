<?php

namespace App\Http\Requests\ContractRenewal;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractRenewalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            && $this->user()->hasRole(['Super Admin', 'HR', 'HOD', 'Admin Divisi'])
            && $this->user()->hasMenuAccess('contract_renewal');
    }

    public function rules(): array
    {
        return [
            'history_id' => 'required|integer|exists:employee_contract_histories,id',
        ];
    }

    public function messages(): array
    {
        return [
            'history_id.required' => 'History kontrak wajib dipilih.',
            'history_id.exists' => 'History kontrak tidak ditemukan.',
        ];
    }
}
