<?php

namespace App\Http\Requests\Approval;

use Illuminate\Foundation\Http\FormRequest;

class FilterApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:100']];
    }

    public function messages(): array
    {
        return ['search.max' => 'Pencarian maksimal 100 karakter.'];
    }
}
