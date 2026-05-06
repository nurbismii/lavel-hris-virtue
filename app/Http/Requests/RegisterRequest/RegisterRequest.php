<?php

namespace App\Http\Requests\RegisterRequest;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function rules()
    {
        return [
            'nik_karyawan' => [
                'required',
                'string',
                'max:50',
                Rule::exists('employees', 'nik')->where(function (Builder $query) {
                    $query->where('status_resign', 'AKTIF')
                        ->whereNull('tgl_resign');
                }),
                Rule::unique('users', 'nik_karyawan'),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'password' => 'required|confirmed|min:8'
        ];
    }

    public function messages()
    {
        return [

            // NIK
            'nik_karyawan.required' => 'NIK karyawan wajib diisi.',
            'nik_karyawan.exists'   => 'NIK karyawan tidak ditemukan atau status karyawan tidak aktif.',
            'nik_karyawan.unique'   => 'NIK karyawan sudah terdaftar di sistem.',

            // Email
            'email.required' => 'Email wajib diisi.',
            'email.email'    => 'Format email tidak valid.',
            'email.unique'   => 'Email sudah digunakan, silakan gunakan email lain.',

            // Password
            'password.required'  => 'Password wajib diisi.',
            'password.min'       => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sesuai.',
        ];
    }
}
