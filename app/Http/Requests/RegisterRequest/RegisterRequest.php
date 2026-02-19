<?php

namespace App\Http\Requests\RegisterRequest;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function rules()
    {
        return [
            'nik_karyawan' => 'required|unique:users,nik_karyawan',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|confirmed|min:6'
        ];
    }

    public function messages()
    {
        return [

            // NIK
            'nik_karyawan.required' => 'NIK karyawan wajib diisi.',
            'nik_karyawan.unique'   => 'NIK karyawan sudah terdaftar di sistem.',

            // Email
            'email.required' => 'Email wajib diisi.',
            'email.email'    => 'Format email tidak valid.',
            'email.unique'   => 'Email sudah digunakan, silakan gunakan email lain.',

            // Password
            'password.required'  => 'Password wajib diisi.',
            'password.min'       => 'Password minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sesuai.',
        ];
    }
}
