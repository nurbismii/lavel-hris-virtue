<?php

namespace App\Http\Requests\Presensi;

use Illuminate\Foundation\Http\FormRequest;

class CloseAttendancePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']);
    }

    public function rules(): array
    {
        return [
            'period_month' => [
                'required',
                'date_format:Y-m',
                function ($attribute, $value, $fail) {
                    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}$/', $value)) {
                        $fail('Format periode cutoff tidak valid.');
                        return;
                    }

                    [$year, $month] = array_map('intval', explode('-', $value));

                    if (!checkdate($month, 1, $year)) {
                        $fail('Bulan periode cutoff tidak valid.');
                    }
                },
            ],
            'close_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'period_month.required' => 'Periode cutoff wajib dipilih.',
            'period_month.date_format' => 'Format periode cutoff tidak valid.',
            'close_note.max' => 'Catatan closing maksimal 500 karakter.',
        ];
    }
}
