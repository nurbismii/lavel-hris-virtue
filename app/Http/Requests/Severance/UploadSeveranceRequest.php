<?php

namespace App\Http\Requests\Severance;

use App\Http\Requests\Salary\UploadSalaryRequest;

class UploadSeveranceRequest extends UploadSalaryRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasMenuAccess('severance_staff');
    }

    public function rules(): array
    {
        return parent::rules() + ['title' => ['required', 'string', 'max:150'],
            'termination_date' => ['required', 'date_format:Y-m-d']];
    }

    public function messages(): array
    {
        return ['file.required' => 'Pilih file pengajuan pesangon.', 'title.required' => 'Isi nama batch pengajuan.',
            'termination_date.required' => 'Tentukan tanggal akhir kerja untuk batch ini.'] + parent::messages();
    }
}
