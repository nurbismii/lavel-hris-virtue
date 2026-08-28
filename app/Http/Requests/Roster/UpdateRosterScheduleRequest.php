<?php

namespace App\Http\Requests\Roster;

use App\Models\RosterSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRosterScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']);
    }

    public function rules(): array
    {
        return [
            'work_start' => ['required', 'date'],
            'work_end' => ['required', 'date', 'after_or_equal:work_start'],
            'off_start' => ['required', 'date', 'after:work_end'],
            'off_end' => ['required', 'date', 'after_or_equal:off_start'],
            'realization_type' => ['required', Rule::in(array_keys(RosterSchedule::realizationOptions()))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'regenerate_following' => ['nullable', 'boolean'],
        ];
    }
}
