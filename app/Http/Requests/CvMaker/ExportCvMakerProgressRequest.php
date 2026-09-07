<?php

namespace App\Http\Requests\CvMaker;

class ExportCvMakerProgressRequest extends StoreReminderBatchRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['idempotency_key'], $rules['selection_mode'], $rules['employee_niks'], $rules['employee_niks.*']);
        return $rules;
    }
}
