<?php

namespace App\Http\Requests\ContractRenewal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviseTerminatedContractRenewalRequest extends FormRequest
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
            'assessment_months' => ['required', 'integer', Rule::in(range(1, 12))],
            'revision_note' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'assessment_months.required' => 'Durasi perpanjangan hasil revisi wajib dipilih.',
            'assessment_months.in' => 'Durasi perpanjangan hasil revisi hanya boleh 1 sampai 12 bulan.',
            'revision_note.required' => 'Alasan revisi putus kontrak wajib diisi.',
            'revision_note.max' => 'Alasan revisi maksimal 1000 karakter.',
        ];
    }
}
