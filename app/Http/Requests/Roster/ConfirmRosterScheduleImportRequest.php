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
        return [
            'file_path' => ['prohibited'],
            'failure_file_path' => ['prohibited'],
            'file_checksum' => ['prohibited'],
            'status' => ['prohibited'],
            'actor_id' => ['prohibited'],
            'confirmed_by' => ['prohibited'],
        ];
    }
}
