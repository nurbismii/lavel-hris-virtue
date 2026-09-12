<?php

namespace App\Http\Requests\SuratPeringatan;

use App\Models\WarningLetterRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarningLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create', WarningLetterRequest::class);
    }

    public function rules(): array
    {
        return [
            'submission_token' => ['required', 'uuid'],
            'nik' => ['required', 'string', 'regex:/^[0-9]+$/', 'max:32'],
            'hod_name' => ['required', 'string', 'max:180'],
            'level_sp' => ['required', Rule::in(array_keys(WarningLetterRequest::levels()))],
            'keterangan' => ['required', 'string', 'max:4000'],
            'pelapor' => ['required', 'string', 'max:128'],
            'tgl_mulai' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.required' => ':attribute wajib diisi.',
            '*.max' => ':attribute maksimal :max karakter.',
            'nik.regex' => 'NIK karyawan harus berupa angka.',
            'level_sp.in' => 'Pilih Surat Peringatan 1, 2, atau 3.',
            '*.date_format' => 'Tanggal harus berformat YYYY-MM-DD.',
            'submission_token.uuid' => 'Form tidak valid. Muat ulang halaman dan coba kembali.',
        ];
    }

    public function attributes(): array
    {
        return ['nik' => 'NIK', 'hod_name' => 'Nama HOD', 'level_sp' => 'Level SP',
            'keterangan' => 'Keterangan pelanggaran', 'pelapor' => 'Nama pelapor',
            'tgl_mulai' => 'Tanggal mulai', 'tgl_berakhir' => 'Tanggal berakhir'];
    }
}
