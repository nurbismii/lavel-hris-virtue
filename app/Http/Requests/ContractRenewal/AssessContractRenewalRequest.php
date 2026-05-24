<?php

namespace App\Http\Requests\ContractRenewal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssessContractRenewalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'assessment_months' => ['required', 'integer', Rule::in(range(1, 12))],
            'assessment_note' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'assessment_months.required' => 'Durasi perpanjangan wajib dipilih.',
            'assessment_months.in' => 'Durasi perpanjangan hanya boleh 1 sampai 12 bulan.',
            'assessment_note.max' => 'Catatan penilaian maksimal 1000 karakter.',
        ];
    }
}
