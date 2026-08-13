<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertJobTitleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']);
    }

    public function rules(): array
    {
        $jobTitle = $this->route('jobTitle');
        $jobTitleId = $jobTitle ? $jobTitle->id : null;

        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('job_titles', 'code')->ignore($jobTitleId)],
            'name' => ['required', 'string', 'max:255'],
            'name_zh' => ['nullable', 'string', 'max:255'],
            'job_level_id' => ['required', 'integer', 'exists:job_levels,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'aliases' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function payload(): array
    {
        $data = $this->validated();
        unset($data['aliases']);
        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $this->boolean('is_active');

        return $data;
    }

    public function aliases(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $this->input('aliases')))
            ->map(fn($alias) => trim($alias))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
