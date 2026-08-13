<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;

class AssignEmployeePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']);
    }

    public function rules(): array
    {
        return [
            'employee_nik' => ['required', 'string', 'max:32', 'exists:employees,nik'],
            'effective_from' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
