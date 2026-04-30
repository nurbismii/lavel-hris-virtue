<?php

namespace App\Http\Requests\Overtime;

use App\Models\OvertimePayRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OvertimePayRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $ruleId = optional($this->route('overtimePayRule'))->id;

        return [
            'code' => [
                'required',
                'string',
                'max:80',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('overtime_pay_rules', 'code')->ignore($ruleId),
            ],
            'name' => ['required', 'string', 'max:160'],
            'schedule_type' => ['required', Rule::in(array_keys(OvertimePayRule::ruleScheduleTypeOptions()))],
            'day_type' => ['required', Rule::in(array_keys(OvertimePayRule::dayTypeOptions()))],
            'hour_from' => ['required', 'integer', 'min:1', 'max:24'],
            'hour_to' => ['nullable', 'integer', 'min:1', 'max:24'],
            'multiplier' => ['required', 'numeric', 'min:0.01', 'max:24'],
            'legal_basis' => ['required', 'string', 'max:160'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hourFrom = (int) $this->input('hour_from');
            $hourTo = $this->filled('hour_to') ? (int) $this->input('hour_to') : null;

            if ($hourTo !== null && $hourTo < $hourFrom) {
                $validator->errors()->add('hour_to', 'Jam akhir harus lebih besar atau sama dengan jam mulai.');
            }

            if (
                $this->input('day_type') === OvertimePayRule::DAY_SHORTEST_WORKDAY_HOLIDAY
                && $this->input('schedule_type') !== OvertimePayRule::SCHEDULE_SIX_ONE
            ) {
                $validator->errors()->add('schedule_type', 'Kategori hari kerja terpendek hanya berlaku untuk pola 6:1.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode rule wajib diisi.',
            'code.unique' => 'Kode rule sudah digunakan.',
            'code.regex' => 'Kode rule hanya boleh berisi huruf, angka, garis bawah, dan tanda hubung.',
            'name.required' => 'Nama rule wajib diisi.',
            'schedule_type.required' => 'Pola rule wajib dipilih.',
            'day_type.required' => 'Jenis hari wajib dipilih.',
            'hour_from.required' => 'Jam mulai wajib diisi.',
            'multiplier.required' => 'Pengali wajib diisi.',
            'legal_basis.required' => 'Dasar hukum wajib diisi.',
        ];
    }

    public function payload(): array
    {
        $validated = $this->validated();

        $validated['code'] = strtoupper($validated['code']);
        $validated['hour_to'] = $validated['hour_to'] ?? null;
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        $validated['is_active'] = $this->boolean('is_active');

        return $validated;
    }
}
