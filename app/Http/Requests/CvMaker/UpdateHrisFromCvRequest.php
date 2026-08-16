<?php

namespace App\Http\Requests\CvMaker;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHrisFromCvRequest extends FormRequest
{
    private const FIELD_KEYS = [
        'name', 'ktp_number', 'family_card_number', 'birth_date', 'gender', 'blood_type',
        'height', 'weight', 'religion', 'marital_status', 'mother_name', 'spouse_name',
        'marriage_date', 'phone', 'ktp_address', 'rt', 'rw', 'domicile_address',
        'npwp_number', 'bank_account_number', 'job_title', 'position', 'entry_date',
        'province', 'regency', 'district', 'village', 'education_level',
        'education_institution', 'education_major', 'graduation_year',
    ];

    private const SECTION_KEYS = [
        'educations', 'experiences', 'organizations', 'certifications', 'languages', 'projects',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selected_fields' => ['nullable', 'array', 'max:40'],
            'selected_fields.*' => ['string', 'distinct', Rule::in(self::FIELD_KEYS)],
            'selected_sections' => ['nullable', 'array', 'max:10'],
            'selected_sections.*' => ['string', 'distinct', Rule::in(self::SECTION_KEYS)],
            'include_organization' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (
                empty($this->input('selected_fields', []))
                && empty($this->input('selected_sections', []))
                && !$this->boolean('include_organization')
            ) {
                $validator->errors()->add('selection', 'Pilih minimal satu field atau bagian yang akan diperbarui.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'selected_fields.*.in' => 'Terdapat field yang tidak diizinkan untuk diperbarui.',
            'selected_sections.*.in' => 'Terdapat bagian riwayat yang tidak diizinkan untuk diperbarui.',
        ];
    }
}
