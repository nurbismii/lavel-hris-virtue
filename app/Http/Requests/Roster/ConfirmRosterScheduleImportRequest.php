<?php

namespace App\Http\Requests\Roster;

use Illuminate\Foundation\Http\FormRequest;

final class ConfirmRosterScheduleImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            && $this->user()->hasRole(['Super Admin', 'HR'])
            && $this->user()->hasMenuAccess('roster_schedule');
    }

    public function rules(): array
    {
        return [];
    }
}
