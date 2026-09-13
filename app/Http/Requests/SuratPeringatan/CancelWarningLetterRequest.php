<?php

namespace App\Http\Requests\SuratPeringatan;

use Illuminate\Foundation\Http\FormRequest;

class CancelWarningLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('delete', $this->route('warningLetter'));
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan penghapusan SP wajib diisi.',
            'reason.min' => 'Alasan penghapusan minimal 10 karakter.',
            'reason.max' => 'Alasan penghapusan maksimal 2000 karakter.',
        ];
    }
}
