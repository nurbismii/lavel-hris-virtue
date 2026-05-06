<?php

namespace App\Http\Requests\Approval;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'integer', Rule::in([1, 2])],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Aksi approval wajib dipilih.',
            'action.in' => 'Aksi approval tidak valid.',
            'note.max' => 'Catatan approval maksimal 500 karakter.',
        ];
    }
}
