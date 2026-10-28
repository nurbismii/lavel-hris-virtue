<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasMenuAccess('perusahaan');
    }

    public function rules(): array
    {
        return [
            'perusahaan_id' => ['required', 'integer', 'exists:perusahaan,id'],
            'departemen' => ['required', 'string', 'max:255'],
            'kepala_dept' => ['required', 'string', 'max:255'],
            'no_telp_departemen' => ['required', 'string', 'max:16'],
            'status_pengeluaran' => ['required', 'in:PRODUKSI 生产,NON PRODUKSI 生产 非生产'],
        ];
    }

    public function attributes(): array
    {
        return ['perusahaan_id' => 'perusahaan', 'departemen' => 'departemen', 'kepala_dept' => 'kepala departemen', 'no_telp_departemen' => 'nomor telepon departemen', 'status_pengeluaran' => 'status pengeluaran'];
    }
}
