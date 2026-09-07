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
            'jabatan' => 'nullable|string|max:123',
            'posisi' => 'nullable|string|max:128',
            'status_karyawan' => 'nullable|string|max:255',
            'jenis_kelamin' => 'nullable|in:L,P',
            'pendidikan_terakhir' => 'nullable|string|max:225',
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
