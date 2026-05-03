<?php

namespace App\Http\Requests\Roster;

use Illuminate\Foundation\Http\FormRequest;

class RosterOffRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            && filled($this->user()->nik_karyawan)
            && $this->user()->hasRole(['Staff Roster', 'Super Admin']);
    }

    public function rules(): array
    {
        return [
            'tanggal_off' => ['required', 'date', 'after_or_equal:today'],
            'alasan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal_off.required' => 'Tanggal OFF wajib diisi.',
            'tanggal_off.date' => 'Tanggal OFF tidak valid.',
            'tanggal_off.after_or_equal' => 'Tanggal OFF tidak boleh sebelum hari ini.',
            'alasan.max' => 'Alasan maksimal 1000 karakter.',
        ];
    }
}
