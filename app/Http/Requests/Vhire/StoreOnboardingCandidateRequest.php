<?php

namespace App\Http\Requests\Vhire;

use App\Models\EmployeeContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnboardingCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vhire_candidate_id' => 'required|string|max:120',
            'candidate_code' => 'required|string|max:120',
            'no_ktp' => ['required', 'string', 'regex:/^[0-9]{16}$/'],
            'nama' => 'required|string|max:180',
            'jenis_kelamin' => 'nullable|string|max:30',
            'status_pernikahan' => 'nullable|string|max:60',
            'alamat' => 'nullable|string|max:2000',
            'jabatan' => 'nullable|string|max:180',
            'tanggal_mulai_kerja' => 'nullable|date',
            'departemen' => 'nullable|string|max:180',
            'lokasi' => 'nullable|string|max:180',
            'kode_kontrak' => 'nullable|string|max:120',
            'no_pkwt' => 'nullable|string|max:120',
            'gaji' => 'nullable|numeric|min:0|max:999999999999',
            'uang_makan' => 'nullable|numeric|min:0|max:999999999999',
            'recruitment_status' => 'nullable|string|max:80',
            'onboarding_status' => 'nullable|string|max:80',
            'contract_duration_value' => 'required|integer|min:1|max:120',
            'contract_duration_unit' => ['required', 'string', Rule::in(['day', 'days', 'hari', 'month', 'months', 'bulan', 'year', 'years', 'tahun'])],
            'signing_method' => ['required', Rule::in(array_keys(EmployeeContract::signingMethodOptions()))],
            'source_updated_at' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'no_ktp.regex' => 'No KTP wajib berisi 16 digit angka.',
            'contract_duration_value.required' => 'Durasi kontrak wajib dikirim dari V-Hire.',
            'contract_duration_unit.in' => 'Satuan durasi kontrak tidak valid.',
            'signing_method.in' => 'Metode tanda tangan harus electronic atau manual.',
        ];
    }
}
