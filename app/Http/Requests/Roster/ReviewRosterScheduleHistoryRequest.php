<?php

namespace App\Http\Requests\Roster;

use App\Models\RosterScheduleHistory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewRosterScheduleHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']);
    }

    public function rules(): array
    {
        return [
            'classification' => [
                'required',
                Rule::in([
                    RosterScheduleHistory::CLASSIFICATION_PLANNED,
                    RosterScheduleHistory::CLASSIFICATION_CUTI,
                    RosterScheduleHistory::CLASSIFICATION_INSENTIF,
                    RosterScheduleHistory::CLASSIFICATION_NOT_APPLICABLE,
                ]),
            ],
            'review_note' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'classification.required' => 'Hasil realisasi histori wajib dipilih.',
            'review_note.required' => 'Catatan review wajib diisi agar perubahan dapat diaudit.',
        ];
    }
}
