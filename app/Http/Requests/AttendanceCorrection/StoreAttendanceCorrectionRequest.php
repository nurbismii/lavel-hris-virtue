<?php

namespace App\Http\Requests\AttendanceCorrection;

use App\Models\AttendanceCorrection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && filled($this->user()->nik_karyawan);
    }

    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'jam_masuk' => ['nullable', 'date_format:H:i'],
            'jam_istirahat' => ['nullable', 'date_format:H:i'],
            'jam_kembali_istirahat' => ['nullable', 'date_format:H:i'],
            'jam_pulang' => ['nullable', 'date_format:H:i'],
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
            $hasTimeChange = collect([
                'jam_masuk',
                'jam_istirahat',
                'jam_kembali_istirahat',
                'jam_pulang',
            ])->contains(fn($field) => filled($this->input($field)));

            if (!$hasTimeChange && blank($this->input('status_presensi'))) {
                $validator->errors()->add('jam_masuk', 'Isi minimal satu jam koreksi atau status presensi yang perlu diperbaiki.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'tanggal.required' => 'Tanggal presensi wajib diisi.',
            'tanggal.before_or_equal' => 'Tanggal koreksi tidak boleh lebih dari hari ini.',
            'jam_masuk.date_format' => 'Format jam masuk harus HH:MM.',
            'jam_istirahat.date_format' => 'Format jam istirahat harus HH:MM.',
            'jam_kembali_istirahat.date_format' => 'Format jam kembali istirahat harus HH:MM.',
            'jam_pulang.date_format' => 'Format jam pulang harus HH:MM.',
            'status_presensi.in' => 'Status presensi yang dipilih tidak valid.',
            'reason.required' => 'Alasan koreksi wajib diisi.',
            'reason.min' => 'Alasan koreksi minimal 10 karakter.',
            'reason.max' => 'Alasan koreksi maksimal 2000 karakter.',
            'attachment.mimes' => 'Bukti lampiran harus berupa JPG, JPEG, PNG, WEBP, atau PDF.',
            'attachment.max' => 'Ukuran bukti lampiran maksimal 5MB.',
        ];
    }
}
