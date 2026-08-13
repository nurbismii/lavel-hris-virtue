<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertJobLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']);
    }

    public function rules(): array
    {
        $level = $this->route('jobLevel');
        $levelId = $level ? $level->id : null;

        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('job_levels', 'code')->ignore($levelId)],
            'name' => ['required', 'string', 'max:100'],
            'rank' => ['required', 'integer', 'min:1', 'max:999', Rule::unique('job_levels', 'rank')->ignore($levelId)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function payload(): array
    {
        $data = $this->validated();
        $data['code'] = strtoupper($data['code']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $this->boolean('is_active');

        return $data;
    }
}
