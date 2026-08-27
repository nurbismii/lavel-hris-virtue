<?php

namespace App\Http\Requests\Roster;

use Illuminate\Foundation\Http\FormRequest;

final class UploadRosterScheduleImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->hasRole(['Super Admin', 'HR'])
            && $user->hasMenuAccess('roster_schedule');
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:' . (int) config('roster.import.max_kb', 10240)],
        ];
    }
}
