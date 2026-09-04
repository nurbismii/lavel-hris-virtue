<?php

namespace App\Http\Requests\CvMaker;

use App\Models\CvMakerPositionSkillCategory;
use App\Services\CvMaker\CvMakerCompareService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReminderBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = max(1, min((int) config('services.cv_maker.reminder_batch_limit', 500), 1000));

        return [
            'idempotency_key' => ['required', 'uuid'],
            'selection_mode' => ['required', Rule::in(['selected', 'filtered'])],
            'employee_niks' => ['required_if:selection_mode,selected', 'array', 'max:' . $max],
            'employee_niks.*' => ['string', 'max:32', 'distinct'],
            'area' => ['nullable', 'array', 'max:50'],
            'area.*' => ['string', 'max:50', 'distinct'],
            'departemen' => ['nullable', 'integer'],
            'divisi' => ['nullable', 'integer'],
            'posisi' => ['nullable', 'array', 'max:100'],
            'posisi.*' => ['string', 'max:255', 'distinct'],
            'jabatan_hris' => ['nullable', 'array', 'max:14'],
            'jabatan_hris.*' => ['string', Rule::in(array_keys(CvMakerCompareService::hrisJobTitlePrefixes())), 'distinct'],
            'jabatan' => ['nullable', 'array', 'max:100'],
            'jabatan.*' => ['string', 'max:255', 'distinct'],
            'cv_skill_category' => ['nullable', Rule::in(array_keys(CvMakerPositionSkillCategory::labels()))],
            'status_resign' => ['nullable', 'string', 'max:80'],
            'cv_reminder' => ['nullable', Rule::in(['needs_reminder', 'not_needed'])],
            'cv_progress_status' => ['nullable', Rule::in(['not_synced', 'no_account', 'no_profile', 'in_progress', 'complete'])],
            'cv_progress_step' => ['nullable', 'array', 'max:8'],
            'cv_progress_step.*' => ['integer', 'between:1,8', 'distinct'],
            'cv_review_status' => ['nullable', Rule::in(['unreviewed', 'in_review', 'needs_employee_confirmation', 'completed'])],
            'search' => ['nullable', 'string', 'max:120'],
        ];
    }
}
