<?php

namespace App\Http\Requests\ElectronicContract;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFirstPartySignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']);
    }

    public function rules(): array
    {
        return [
            'signature_mode' => ['required', Rule::in(['draw', 'upload'])],
            'signature_data' => [
                'required_if:signature_mode,draw',
                'nullable',
                'string',
                'regex:/^data:image\/png;base64,/',
                'max:1500000',
            ],
            'signature_file' => [
                'required_if:signature_mode,upload',
                'nullable',
                'file',
                'mimes:jpg,jpeg,png',
                'max:2048',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'signature_mode.required' => 'Pilih metode input tanda tangan.',
            'signature_mode.in' => 'Metode input tanda tangan tidak valid.',
            'signature_data.required_if' => 'Gambar tanda tangan wajib diisi.',
            'signature_data.regex' => 'Format tanda tangan gambar langsung tidak valid.',
            'signature_file.required_if' => 'File tanda tangan wajib diunggah.',
            'signature_file.mimes' => 'File tanda tangan harus berupa JPG atau PNG.',
            'signature_file.max' => 'Ukuran file tanda tangan maksimal 2 MB.',
        ];
    }
}
