<?php

namespace App\Http\Requests\CvMaker;

use Illuminate\Validation\Rule;

class StorePdfBatchRequest extends ExportCvMakerProgressRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'idempotency_key' => ['required', 'uuid'],
            'mode' => ['required', Rule::in(['single', 'filtered'])],
            'employee_nik' => ['required_if:mode,single', 'nullable', 'string', 'max:32'],
            'allow_downloaded' => ['sometimes', 'boolean'],
            'pdf_status' => ['nullable', Rule::in(['not_downloaded', 'processing', 'downloaded'])],
        ]);
    }
}
