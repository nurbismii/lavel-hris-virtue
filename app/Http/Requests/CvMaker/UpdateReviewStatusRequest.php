<?php

namespace App\Http\Requests\CvMaker;

use App\Models\CvMakerProgressStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReviewStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'review_status' => ['required', Rule::in(array_keys(CvMakerProgressStatus::reviewLabels()))],
            'review_note' => [
                Rule::requiredIf($this->input('review_status') === CvMakerProgressStatus::REVIEW_NEEDS_CONFIRMATION),
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }
}
