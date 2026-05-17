<?php

namespace App\Http\Requests\ElectronicContract;

use App\Models\EmployeeContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualSignedContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']);
    }

    public function rules(): array
    {
        return [
            'manual_signed_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'manual_verification_status' => [
                'required',
                Rule::in(array_keys(EmployeeContract::manualVerificationStatusOptions())),
            ],
            'manual_note' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'manual_signed_file.required' => 'File kontrak manual yang sudah ditandatangani wajib diunggah.',
            'manual_signed_file.mimes' => 'File kontrak manual harus berupa PDF, JPG, JPEG, atau PNG.',
            'manual_signed_file.max' => 'Ukuran file kontrak manual maksimal 5MB.',
        ];
    }
}
