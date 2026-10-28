<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;

class StoreDivisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasMenuAccess('perusahaan');
    }

    public function rules(): array
    {
        return [
            'departemen_id' => ['required', 'integer', 'exists:departemens,id'],
            'nama_divisi' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['departemen_id' => 'departemen', 'nama_divisi' => 'nama divisi'];
    }
}
