<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSalaryComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR']) && $this->user()->hasMenuAccess('slip_gaji_admin');
    }

    public function rules(): array
    {
        if ($this->route('component')) {
            return ['name'=>['required','string','max:100'], 'revision'=>['required','integer','min:1']];
        }
        return [
            'code'=>['required','string','max:40','regex:/^[A-Z][A-Z0-9_]*$/',Rule::unique('salary_components', 'code')],
            'name'=>['required','string','max:100'], 'type'=>['required',Rule::in(['income','deduction'])], 'fixed'=>['required','boolean'],
        ];
    }

    public function messages(): array
    {
        return ['code.unique'=>'Kode sudah digunakan, termasuk oleh komponen di Tempat Sampah. Pulihkan komponen tersebut atau gunakan kode lain.',
            'code.regex'=>'Kode harus diawali huruf besar, diikuti huruf besar, angka, atau garis bawah.'];
    }
}
