<?php

namespace App\Http\Requests\Payroll;

use App\Http\Requests\Salary\UploadSalaryRequest;

class StorePayrollRequest extends UploadSalaryRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'employer_id'=>['required','integer','exists:perusahaan,id'],
            'income_source'=>['sometimes','in:excel,hris'],
            'period'=>['required','date_format:Y-m','after_or_equal:2026-01','before_or_equal:2026-12'],
            'tax_period'=>['required','date_format:Y-m','after_or_equal:2026-01','before_or_equal:2026-12'],
            'start_date'=>['required','date_format:Y-m-d'],
            'end_date'=>['required','date_format:Y-m-d','after_or_equal:start_date'],
            'pay_date'=>['required','date_format:Y-m-d','after_or_equal:start_date'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), ['file.required'=>'Pilih file komponen payroll.']);
    }
}
