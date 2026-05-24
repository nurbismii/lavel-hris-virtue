<?php

namespace App\Http\Requests\ContractRenewal;

use Illuminate\Foundation\Http\FormRequest;

class DelegateContractRenewalRequest extends FormRequest
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
            'delegate_user_id' => 'required|string|max:64|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'delegate_user_id.required' => 'Delegasi penilaian wajib dipilih.',
            'delegate_user_id.exists' => 'User delegasi tidak ditemukan.',
        ];
    }
}
