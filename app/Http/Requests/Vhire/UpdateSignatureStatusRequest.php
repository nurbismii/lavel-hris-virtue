<?php

namespace App\Http\Requests\Vhire;

use App\Models\EmployeeContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSignatureStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hris_contract_id' => 'nullable|required_without_all:kode_kontrak,no_pkwt|integer',
            'kode_kontrak' => 'nullable|required_without_all:hris_contract_id,no_pkwt|string|max:120',
            'no_pkwt' => 'nullable|required_without_all:hris_contract_id,kode_kontrak|string|max:120',
            'vhire_candidate_id' => 'required|string|max:120',
            'candidate_code' => 'required|string|max:120',
            'no_ktp' => ['required', 'string', 'regex:/^[0-9]{16}$/'],
            'signature_status' => ['required', Rule::in([
                EmployeeContract::SIGNATURE_STATUS_WAITING,
                EmployeeContract::SIGNATURE_STATUS_SIGNED,
                EmployeeContract::SIGNATURE_STATUS_REJECTED,
                EmployeeContract::SIGNATURE_STATUS_CANCELLED,
            ])],
            'status_tanda_tangan' => 'nullable|string|max:80',
            'signed_at' => 'nullable|date',
            'signed_by_source' => ['nullable', Rule::in(['vhire', 'manual_upload', 'admin'])],
        ];
    }

    public function messages(): array
    {
        return [
            'no_ktp.regex' => 'No KTP wajib berisi 16 digit angka.',
            'signature_status.in' => 'Status tanda tangan tidak valid.',
        ];
    }
}
