<?php

namespace App\Http\Requests\Roster;

use Illuminate\Foundation\Http\FormRequest;

class StoreRosterScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']);
    }

    public function rules(): array
    {
        return [
            'employee_nik' => ['required', 'string', 'max:50', 'exists:employees,nik'],
            'work_start' => ['required', 'date'],
            'cycles' => ['required', 'integer', 'min:1', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_nik.required' => 'Karyawan wajib dipilih.',
            'employee_nik.exists' => 'Karyawan tidak ditemukan.',
            'work_start.required' => 'Tanggal mulai 10 minggu kerja wajib diisi.',
            'cycles.required' => 'Jumlah jadwal yang akan dibuat wajib diisi.',
            'cycles.max' => 'Maksimal 60 jadwal per proses.',
        ];
    }
}
