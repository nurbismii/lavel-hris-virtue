<?php

namespace App\Http\Requests\Roster;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && filled($this->user()->nik_karyawan);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'no_telp' => ['required', 'string', 'max:30'],
            'periode_awal' => ['required', 'date'],
            'periode_akhir' => ['required', 'date', 'after_or_equal:periode_awal'],
            'tipe_rencana' => ['required', Rule::in(['1', '2'])],
            'roster_schedule_id' => ['nullable', 'integer', 'exists:roster_schedules,id'],
            'berkas_cuti' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'no_telp.required' => 'Nomor HP wajib diisi.',
            'periode_awal.required' => 'Periode awal wajib diisi.',
            'periode_akhir.required' => 'Periode akhir wajib diisi.',
            'periode_akhir.after_or_equal' => 'Periode akhir tidak boleh sebelum periode awal.',
            'tipe_rencana.required' => 'Jenis rencana roster wajib dipilih.',
            'tipe_rencana.in' => 'Jenis rencana roster tidak valid.',
            'berkas_cuti.mimes' => 'Berkas roster harus berupa JPG, PNG, atau PDF.',
            'berkas_cuti.max' => 'Ukuran berkas roster maksimal 4MB.',
        ];
    }
}
