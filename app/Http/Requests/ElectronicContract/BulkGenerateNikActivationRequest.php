<?php

namespace App\Http\Requests\ElectronicContract;

use Illuminate\Foundation\Http\FormRequest;

class BulkGenerateNikActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']);
    }

    public function rules(): array
    {
        return [
            'contract_ids' => 'required|array|min:1|max:200',
            'contract_ids.*' => 'required|integer|distinct|exists:employee_contracts,id',
        ];
    }

    public function messages(): array
    {
        return [
            'contract_ids.required' => 'Pilih minimal satu kontrak untuk generate NIK.',
            'contract_ids.array' => 'Format pilihan kontrak tidak valid.',
            'contract_ids.min' => 'Pilih minimal satu kontrak untuk generate NIK.',
            'contract_ids.max' => 'Generate NIK massal dibatasi maksimal 200 kontrak per proses.',
            'contract_ids.*.integer' => 'Pilihan kontrak tidak valid.',
            'contract_ids.*.distinct' => 'Ada kontrak yang dipilih lebih dari satu kali.',
            'contract_ids.*.exists' => 'Salah satu kontrak yang dipilih tidak ditemukan.',
        ];
    }

    public function contractIds(): array
    {
        return collect($this->input('contract_ids', []))
            ->map(fn($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
