<?php

namespace App\Http\Requests\Roster;

use App\Models\RosterSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualRosterSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']);
    }

    public function rules(): array
    {
        return [
            'realization_type' => [
                'required',
                Rule::in([
                    RosterSchedule::REALIZATION_CUTI,
                    RosterSchedule::REALIZATION_INSENTIF,
                ]),
            ],
            'manual_reference_number' => ['nullable', 'string', 'max:100'],
            'manual_submission_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'realization_type.required' => 'Jenis realisasi wajib dipilih.',
            'realization_type.in' => 'Jenis realisasi hanya boleh Cuti Roster atau Insentif.',
            'manual_reference_number.max' => 'Nomor referensi maksimal 100 karakter.',
            'manual_submission_note.max' => 'Catatan pengajuan maksimal 500 karakter.',
        ];
    }
}
