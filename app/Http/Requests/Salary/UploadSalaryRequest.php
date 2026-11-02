<?php

namespace App\Http\Requests\Salary;

use Illuminate\Foundation\Http\FormRequest;

class UploadSalaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['Super Admin', 'HR'])
            && $this->user()->hasMenuAccess('slip_gaji_admin');
    }

    public function rules(): array
    {
        return ['file' => ['bail', 'required', 'file', 'mimes:xlsx',
            'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'max:' . (int) config('salary.import.max_kb', 5120),
            function ($attribute, $file, $fail) {
                if (strtolower($file->getClientOriginalExtension()) !== 'xlsx') {
                    $fail('Ekstensi file harus .xlsx.');
                    return;
                }
                $zip = new \ZipArchive();
                if ($zip->open($file->getRealPath()) !== true) {
                    $fail('File harus berupa workbook XLSX yang valid.');
                    return;
                }
                if ($zip->numFiles > 2000) {
                    $zip->close();
                    $fail('Workbook memiliki terlalu banyak komponen. Gunakan template baru.');
                    return;
                }
                $size = 0;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $size += (int) ($zip->statIndex($i)['size'] ?? 0);
                }
                $valid = $zip->locateName('[Content_Types].xml') !== false
                    && $zip->numFiles <= 2000 && $size <= 50 * 1024 * 1024;
                $zip->close();
                if (!$valid) {
                    $fail('Workbook tidak valid atau terlalu besar setelah diekstrak. Gunakan template dan hapus sheet yang tidak diperlukan.');
                }
            },
        ]];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Pilih file pembaruan gaji.',
            'file.mimes' => 'Gunakan file XLSX sesuai template.',
            'file.mimetypes' => 'Isi file harus berupa workbook XLSX.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ];
    }
}
