<?php

namespace App\Http\Requests\SuratPeringatan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewWarningLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('review', $this->route('warningLetter'));
    }

    public function rules(): array
    {
        return ['decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:2000']];
    }

    public function messages(): array
    {
        return ['decision.required' => 'Pilih keputusan approval.', 'decision.in' => 'Keputusan tidak valid.',
            'reason.required_if' => 'Alasan penolakan wajib diisi.', 'reason.max' => 'Alasan maksimal 2000 karakter.'];
    }
}
