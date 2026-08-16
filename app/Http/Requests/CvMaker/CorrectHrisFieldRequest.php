<?php

namespace App\Http\Requests\CvMaker;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorrectHrisFieldRequest extends FormRequest
{
    private const ALLOWED_FIELDS = [
        'name', 'ktp_number', 'family_card_number', 'birth_date', 'gender', 'blood_type',
        'height', 'weight', 'religion', 'marital_status', 'mother_name', 'spouse_name',
        'marriage_date', 'phone', 'ktp_address', 'rt', 'rw', 'domicile_address',
        'npwp_number', 'bank_account_number', 'entry_date', 'education_level',
        'education_institution', 'education_major', 'graduation_year',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'field_key' => ['required', 'string', Rule::in(self::ALLOWED_FIELDS)],
            'value' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'field_key.in' => 'Field ini tidak dapat dikoreksi langsung.',
            'value.required' => 'Nilai koreksi wajib diisi.',
        ];
    }
}
