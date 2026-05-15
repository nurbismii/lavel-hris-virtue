<?php

namespace App\Http\Requests\ElectronicContract;

use Illuminate\Foundation\Http\FormRequest;

class UploadContractTemplateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']);
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:jpg,jpeg,png,webp|max:2048',
            'contract_template_id' => 'nullable|integer|exists:contract_templates,id',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File gambar wajib dipilih.',
            'file.mimes' => 'Gambar KOP/logo harus berformat JPG, PNG, atau WEBP.',
            'file.max' => 'Ukuran gambar maksimal 2MB.',
        ];
    }
}
