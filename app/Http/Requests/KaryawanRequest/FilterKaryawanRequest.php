<?php

namespace App\Http\Requests\KaryawanRequest;

use Illuminate\Foundation\Http\FormRequest;

class FilterKaryawanRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() !== null;
    }

    public function rules()
    {
        return [
            'jabatan' => 'nullable|array|max:100',
            'jabatan.*' => 'required|string|max:123|distinct',
            'posisi' => 'nullable|array|max:100',
            'posisi.*' => 'required|string|max:128|distinct',
            'status_karyawan' => 'nullable|string|max:255',
            'jenis_kelamin' => 'nullable|in:L,P',
            'pendidikan_terakhir' => ['nullable', 'string', \Illuminate\Validation\Rule::in(array_keys(config('employee_filters.education_levels', [])))],
            'entry_date_from' => 'nullable|date_format:Y-m-d',
            'entry_date_to' => 'nullable|date_format:Y-m-d' . ($this->filled('entry_date_from') ? '|after_or_equal:entry_date_from' : ''),
        ];
    }

    public function messages()
    {
        return [
            'entry_date_to.after_or_equal' => 'Tanggal masuk akhir harus sama atau setelah tanggal awal.',
            '*.date_format' => 'Format tanggal harus YYYY-MM-DD.',
            '*.string' => 'Pilihan filter tidak valid.',
            '*.max' => 'Nilai filter terlalu panjang.',
            'jenis_kelamin.in' => 'Pilihan jenis kelamin tidak valid.',
        ];
    }
}
