<?php

namespace App\Http\Requests\Organization;

use App\Models\Perusahaan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertOrganizationPositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']);
    }

    public function rules(): array
    {
        $position = $this->route('organizationPosition');
        $positionId = $position ? $position->id : null;

        return [
            'code' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('organization_positions', 'code')->ignore($positionId)],
            'position_name' => ['required', 'string', 'max:255'],
            'perusahaan_id' => [
                'required',
                'integer',
                Rule::exists('perusahaan', 'id')->where(function ($query) {
                    $query->whereIn('kode_perusahaan', Perusahaan::ORGANIZATION_COMPANY_CODES);
                }),
            ],
            'departemen_id' => ['required', 'integer', 'exists:departemens,id'],
            'divisi_id' => ['nullable', 'integer', 'exists:divisis,id'],
            'job_title_id' => ['required', 'integer', 'exists:job_titles,id'],
            'job_level_id' => ['nullable', 'integer', 'exists:job_levels,id'],
            'parent_position_id' => ['nullable', 'integer', 'exists:organization_positions,id'],
            'planned_headcount' => ['required', 'integer', 'min:1', 'max:10000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function payload(): array
    {
        $data = $this->validated();
        $data['code'] = strtoupper($data['code']);
        $data['position_name'] = trim($data['position_name']);
        $data['divisi_id'] = $data['divisi_id'] ?? null;
        $data['job_level_id'] = $data['job_level_id'] ?? null;
        $data['parent_position_id'] = $data['parent_position_id'] ?? null;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $this->boolean('is_active');

        return $data;
    }
}
