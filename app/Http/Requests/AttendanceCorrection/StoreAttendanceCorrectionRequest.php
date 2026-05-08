<?php

namespace App\Http\Requests\AttendanceCorrection;

use App\Models\AttendanceCorrection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceCorrectionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'request_type' => $this->input('request_type', AttendanceCorrection::REQUEST_TYPE_CORRECTION),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null && filled($this->user()->nik_karyawan);
    }

    public function rules(): array
    {
        return [
            'request_type' => [
                'required',
                'string',
                Rule::in(array_keys(AttendanceCorrection::requestTypeOptions())),
            ],
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'jam_masuk' => ['nullable', 'date_format:H:i'],
            'jam_istirahat' => ['nullable', 'date_format:H:i'],
            'jam_kembali_istirahat' => ['nullable', 'date_format:H:i'],
            'jam_pulang' => ['nullable', 'date_format:H:i'],
            'partial_permission_type' => [
                'nullable',
                'string',
                Rule::in(array_keys(AttendanceCorrection::partialPermissionOptions())),
            ],
            'partial_permission_period' => [
                'nullable',
                'string',
                Rule::in(array_keys(AttendanceCorrection::halfDayPeriodOptions())),
            ],
            'status_presensi' => [
                'nullable',
                'string',
                'max:100',
                Rule::in(array_merge(['__clear__'], array_keys(AttendanceCorrection::statusPresensiOptions()))),
            ],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $requestType = $this->input('request_type', AttendanceCorrection::REQUEST_TYPE_CORRECTION);
            $hasTimeChange = collect([
                'jam_masuk',
                'jam_istirahat',
                'jam_kembali_istirahat',
                'jam_pulang',
            ])->contains(fn($field) => filled($this->input($field)));

            if ($requestType === AttendanceCorrection::REQUEST_TYPE_CORRECTION
                && !$hasTimeChange
                && blank($this->input('status_presensi'))) {
                $validator->errors()->add('jam_masuk', 'Isi minimal satu jam koreksi atau status presensi yang perlu diperbaiki.');
            }

            if ($requestType !== AttendanceCorrection::REQUEST_TYPE_PARTIAL_PERMISSION) {
                return;
            }

            if (blank($this->input('partial_permission_type'))) {
                $validator->errors()->add('partial_permission_type', 'Kategori izin presensi parsial wajib dipilih.');
            }

            if (filled($this->input('status_presensi'))) {
                $validator->errors()->add('status_presensi', 'Status harian khusus tidak digunakan untuk izin presensi parsial.');
            }

            if ($this->input('partial_permission_type') === AttendanceCorrection::PARTIAL_HALF_DAY
                && blank($this->input('partial_permission_period'))) {
                $validator->errors()->add('partial_permission_period', 'Pilih setengah hari pagi atau setengah hari siang.');
            }

            if ($this->input('partial_permission_type') === AttendanceCorrection::PARTIAL_SICK
                && !$this->hasFile('attachment')) {
                $validator->errors()->add('attachment', 'Izin sakit wajib melampirkan surat keterangan sakit.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'request_type.required' => 'Jenis pengajuan wajib dipilih.',
            'request_type.in' => 'Jenis pengajuan tidak valid.',
            'tanggal.required' => 'Tanggal presensi wajib diisi.',
            'tanggal.before_or_equal' => 'Tanggal koreksi tidak boleh lebih dari hari ini.',
            'jam_masuk.date_format' => 'Format jam masuk harus HH:MM.',
            'jam_istirahat.date_format' => 'Format jam istirahat harus HH:MM.',
            'jam_kembali_istirahat.date_format' => 'Format jam kembali istirahat harus HH:MM.',
            'jam_pulang.date_format' => 'Format jam pulang harus HH:MM.',
            'partial_permission_type.in' => 'Kategori izin presensi parsial tidak valid.',
            'partial_permission_period.in' => 'Periode izin 0.5 tidak valid.',
            'status_presensi.in' => 'Status presensi yang dipilih tidak valid.',
            'reason.required' => 'Alasan koreksi wajib diisi.',
            'reason.min' => 'Alasan koreksi minimal 10 karakter.',
            'reason.max' => 'Alasan koreksi maksimal 2000 karakter.',
            'attachment.mimes' => 'Bukti lampiran harus berupa JPG, JPEG, PNG, WEBP, atau PDF.',
            'attachment.max' => 'Ukuran bukti lampiran maksimal 5MB.',
        ];
    }
}
