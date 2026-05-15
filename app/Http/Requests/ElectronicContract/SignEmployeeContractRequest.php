<?php

namespace App\Http\Requests\ElectronicContract;

use Illuminate\Foundation\Http\FormRequest;

class SignEmployeeContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && filled($this->user()->nik_karyawan);
    }

    public function rules(): array
    {
        return [
            'consent' => 'accepted',
            'signature_data' => [
                'required',
                'string',
                'regex:/^data:image\/png;base64,/',
                'max:1500000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'consent.accepted' => 'Anda perlu menyetujui pernyataan sebelum menandatangani.',
            'signature_data.required' => 'Tanda tangan wajib diisi.',
            'signature_data.regex' => 'Format tanda tangan tidak valid.',
        ];
    }
}
