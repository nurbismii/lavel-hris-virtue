<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class PasskeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->routeIs('passkeys.login.*') || $this->user() !== null;
    }

    public function rules(): array
    {
        if ($this->routeIs('passkeys.register.options')) {
            return [
                'name' => ['required', 'string', 'max:80'],
                'current_password' => ['required', 'string', 'max:1024', 'current_password:web'],
            ];
        }
        if ($this->routeIs('passkeys.destroy')) {
            return ['current_password' => ['required', 'string', 'max:1024', 'current_password:web']];
        }
        if ($this->routeIs('passkeys.login.options')) {
            return ['remember' => ['nullable', 'boolean'], 'redirect' => ['nullable', 'string', 'max:2048']];
        }

        $binary = ['required', 'string', 'regex:/\A[A-Za-z0-9_-]+\z/'];
        $rules = [
            'challenge_id' => ['required', 'string', 'size:64', 'regex:/\A[a-f0-9]+\z/'],
            'id' => array_merge($binary, ['max:1400']),
            'type' => ['required', 'in:public-key'],
            'response' => ['required', 'array'],
            'response.clientDataJSON' => array_merge($binary, ['max:8192']),
        ];

        if ($this->routeIs('passkeys.register.store')) {
            $rules['response.attestationObject'] = array_merge($binary, ['max:65536']);
        } else {
            $rules['response.authenticatorData'] = array_merge($binary, ['max:8192']);
            $rules['response.signature'] = array_merge($binary, ['max:2048']);
            $rules['response.userHandle'] = array_merge($binary, ['max:128']);
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => 'Password saat ini tidak sesuai.',
            'current_password.required' => 'Masukkan password saat ini untuk mengubah passkey.',
            'name.required' => 'Berikan nama agar passkey mudah dikenali.',
            'name.max' => 'Nama passkey maksimal 80 karakter.',
            '*.required' => 'Data passkey belum lengkap. Silakan mulai lagi.',
        ];
    }
}
