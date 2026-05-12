<?php

namespace App\Http\Requests\Approval;

use App\Models\ApprovalDelegation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApprovalDelegationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['required', 'string', Rule::in(array_keys(ApprovalDelegation::moduleLabels()))],
            'departemen_id' => ['nullable', 'integer', 'exists:departemens,id'],
            'divisi_id' => ['nullable', 'integer', 'exists:divisis,id'],
            'delegate_user_id' => ['required', 'string', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'modules.required' => 'Minimal satu modul delegasi wajib dipilih.',
            'modules.array' => 'Pilihan modul delegasi tidak valid.',
            'modules.min' => 'Minimal satu modul delegasi wajib dipilih.',
            'modules.*.in' => 'Modul delegasi tidak valid.',
            'departemen_id.exists' => 'Departemen delegasi tidak valid.',
            'divisi_id.exists' => 'Divisi delegasi tidak valid.',
            'delegate_user_id.required' => 'Karyawan delegasi wajib dipilih.',
            'delegate_user_id.exists' => 'Karyawan delegasi tidak valid.',
        ];
    }
}
