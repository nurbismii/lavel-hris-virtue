<?php

namespace App\Http\Requests\ElectronicContract;

use App\Models\ContractClause;
use App\Models\ContractTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']);
    }

    public function rules(): array
    {
        return [
            'nik' => 'required|string|max:100|exists:employees,nik',
            'contract_type' => ['required', Rule::in(array_keys(ContractTemplate::typeOptions()))],
            'contract_template_id' => 'required|integer|exists:contract_templates,id',
            'contract_number' => 'nullable|string|max:120',
            'contract_code' => 'nullable|string|max:120',
            'pkwt_number' => 'required|string|max:120',
            'gender' => 'nullable|string|max:30',
            'marital_status' => 'nullable|string|max:60',
            'address' => 'nullable|string|max:2000',
            'position' => 'nullable|string|max:150',
            'contract_duration' => 'nullable|string|max:120',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date|after_or_equal:contract_start_date',
            'first_extension_duration' => 'nullable|required_if:contract_type,' . ContractTemplate::TYPE_ADDENDUM_PKWT . '|string|max:120',
            'first_extension_end_date' => 'nullable|required_if:contract_type,' . ContractTemplate::TYPE_ADDENDUM_PKWT . '|date',
            'salary' => 'nullable|numeric|min:0|max:999999999999',
            'meal_allowance' => 'nullable|numeric|min:0|max:999999999999',
            'clause_key' => [
                'nullable',
                'required_if:contract_type,' . ContractTemplate::TYPE_ADDENDUM_PKWT,
                Rule::in(array_keys(ContractClause::keyOptions())),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nik.required' => 'Karyawan wajib dipilih.',
            'pkwt_number.required' => 'Nomor PKWT wajib diisi.',
            'contract_template_id.required' => 'Template kontrak wajib dipilih.',
            'first_extension_duration.required_if' => 'Durasi perpanjangan wajib diisi untuk adendum.',
            'first_extension_end_date.required_if' => 'Tanggal perpanjangan pertama berakhir wajib diisi untuk adendum.',
            'clause_key.required_if' => 'Klausul wajib dipilih untuk adendum pertama. Jika adendum berikutnya, sistem otomatis memakai Klausul 2.',
        ];
    }
}
