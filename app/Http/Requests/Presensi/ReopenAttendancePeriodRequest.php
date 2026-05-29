<?php

namespace App\Http\Requests\Presensi;

use Illuminate\Foundation\Http\FormRequest;

class ReopenAttendancePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']);
    }

    public function rules(): array
    {
        return [
            'reopen_note' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reopen_note.required' => 'Alasan buka ulang periode wajib diisi.',
            'reopen_note.max' => 'Alasan buka ulang maksimal 500 karakter.',
        ];
    }
}
