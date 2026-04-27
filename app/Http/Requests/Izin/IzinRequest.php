<?php

namespace App\Http\Requests\Izin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IzinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && filled($this->user()->nik_karyawan);
    }

    public function rules(): array
    {
        return [
            'tipe' => ['required', Rule::in(['PAID', 'UNPAID'])],
            'tipe_izin' => ['required_if:tipe,PAID', 'nullable', 'string', 'max:150'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_berakhir' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipe.required' => 'Jenis izin wajib dipilih.',
            'tipe.in' => 'Jenis izin tidak valid.',
            'tipe_izin.required_if' => 'Kategori izin berbayar wajib dipilih.',
            'tanggal_mulai.required' => 'Tanggal mulai izin wajib diisi.',
            'tanggal_berakhir.required' => 'Tanggal berakhir izin wajib diisi.',
            'tanggal_berakhir.after_or_equal' => 'Tanggal berakhir tidak boleh sebelum tanggal mulai.',
            'foto.image' => 'Bukti izin harus berupa gambar.',
            'foto.max' => 'Ukuran bukti izin maksimal 2MB.',
        ];
    }
}
