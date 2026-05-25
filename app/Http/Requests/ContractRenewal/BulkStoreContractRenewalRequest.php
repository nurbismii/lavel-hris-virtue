<?php

namespace App\Http\Requests\ContractRenewal;

use App\Models\EmployeeContractRenewal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStoreContractRenewalRequest extends FormRequest
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
            'history_ids' => ['required', 'array', 'min:1', 'max:100'],
            'history_ids.*' => ['required', 'integer', 'distinct', 'exists:employee_contract_histories,id'],
            'bulk_action' => ['required', Rule::in(['create_workflow', 'hod_direct'])],
            'assessment_months' => ['required_if:bulk_action,hod_direct', 'nullable', 'integer', Rule::in(EmployeeContractRenewal::assessmentDecisionValues())],
            'assessment_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'history_ids.required' => 'Pilih minimal satu kontrak yang akan diproses.',
            'history_ids.max' => 'Maksimal 100 kontrak dapat diproses dalam satu kali aksi.',
            'history_ids.*.exists' => 'Salah satu history kontrak tidak ditemukan.',
            'history_ids.*.distinct' => 'History kontrak tidak boleh dipilih berulang.',
            'bulk_action.required' => 'Aksi kolektif wajib dipilih.',
            'bulk_action.in' => 'Aksi kolektif tidak valid.',
            'assessment_months.required_if' => 'Durasi perpanjangan atau keputusan putus kontrak wajib dipilih untuk penilaian HOD kolektif.',
            'assessment_months.in' => 'Pilihan hanya boleh 1 sampai 12 bulan atau PUTUS KONTRAK.',
            'assessment_note.max' => 'Catatan penilaian maksimal 1000 karakter.',
        ];
    }
}
