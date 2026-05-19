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
            'alamat_ktp' => 'nullable|string|max:2000',
            'alamat_domisili' => 'nullable|string|max:2000',
            'jabatan' => 'nullable|string|max:180',
            'tanggal_mulai_kerja' => 'nullable|date',
            'tanggal_akhir_kontrak' => 'nullable|date',
            'departemen' => 'nullable|string|max:180',
            'divisi' => 'nullable|string|max:180',
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
            'nama_ibu_kandung' => 'nullable|string|max:180',
            'nama_bapak' => 'nullable|string|max:180',
            'nama_suami_atau_istri' => 'nullable|string|max:180',
            'agama' => 'nullable|string|max:50',
            'no_kk' => 'nullable|string|max:32',
            'kode_area_kerja' => 'required|string|max:50',
            'status_karyawan' => 'nullable|string|max:80',
            'no_telp' => 'nullable|string|max:30',
            'tanggal_lahir' => 'nullable|date',
            'tgl_lahir' => 'nullable|date',
            'rt' => 'nullable|string|max:10',
            'rw' => 'nullable|string|max:10',
            'kode_pos' => 'nullable|string|max:20',
            'golongan_darah' => 'nullable|string|max:10',
            'npwp' => 'nullable|string|max:50',
            'status_pajak' => 'nullable|string|max:50',
            'bpjs_kesehatan' => 'nullable|string|max:50',
            'bpjs_tk' => 'nullable|string|max:50',
            'jam_kerja' => 'nullable|string|max:50',
            'skill' => 'nullable|string|max:180',
            'tinggi' => 'nullable|string|max:20',
            'berat' => 'nullable|string|max:20',
            'hobi' => 'nullable|string|max:180',
            'no_jamsostek' => 'nullable|string|max:50',
            'no_asuransi' => 'nullable|string|max:50',
            'no_kartu_asuransi' => 'nullable|string|max:50',
            'nama_bank' => 'nullable|string|max:120',
            'no_rekening' => 'nullable|string|max:80',
            'nama_instansi_pendidikan' => 'nullable|string|max:180',
            'pendidikan_terakhir' => 'nullable|string|max:80',
            'jurusan' => 'nullable|string|max:120',
            'tanggal_menikah' => 'nullable|date',
            'sisa_cuti' => 'nullable|numeric|min:0|max:999',
            'sisa_cuti_covid' => 'nullable|numeric|min:0|max:999',
        ];
    }

    public function messages(): array
    {
        return [
            'no_ktp.regex' => 'No KTP wajib berisi 16 digit angka.',
            'kode_area_kerja.required' => 'Kode area kerja harus diisi',
            'contract_duration_value.required' => 'Durasi kontrak wajib dikirim dari V-Hire.',
            'contract_duration_unit.in' => 'Satuan durasi kontrak tidak valid.',
            'signing_method.in' => 'Metode tanda tangan harus electronic atau manual.',
        ];
    }
}
