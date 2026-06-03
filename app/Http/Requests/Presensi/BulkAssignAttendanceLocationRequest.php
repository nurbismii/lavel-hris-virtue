<?php

namespace App\Http\Requests\Presensi;

use Illuminate\Foundation\Http\FormRequest;

class BulkAssignAttendanceLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasMenuAccess('setting_lokasi_presensi');
    }

    public function rules(): array
    {
        return self::baseRules(true);
    }

    public static function baseRules(bool $requireConfirmation = false): array
    {
        $rules = [
            'bulk_lokasi_absen_id' => 'required|integer|exists:lokasi_absens,id',
            'bulk_perusahaan_id' => 'nullable|integer|exists:perusahaan,id',
            'bulk_departemen_id' => 'nullable|integer|exists:departemens,id',
            'bulk_divisi_id' => 'nullable|integer|exists:divisis,id',
            'bulk_effective_from' => 'required|date',
            'bulk_effective_until' => 'nullable|date|after_or_equal:bulk_effective_from',
            'bulk_note' => 'nullable|string|max:255',
            'bulk_employee_niks' => 'nullable|string|max:20000',
            'bulk_assignment_mode' => 'nullable|string|in:replace,append',
        ];

        if ($requireConfirmation) {
            $rules['confirm_bulk_assignment'] = 'accepted';
        }

        return $rules;
    }

    public function messages(): array
    {
        return self::customMessages();
    }

    public static function customMessages(): array
    {
        return [
            'bulk_lokasi_absen_id.required' => 'Lokasi presensi tujuan wajib dipilih.',
            'bulk_lokasi_absen_id.exists' => 'Lokasi presensi tujuan tidak valid.',
            'bulk_effective_from.required' => 'Tanggal mulai berlaku wajib diisi.',
            'bulk_effective_from.date' => 'Tanggal mulai berlaku tidak valid.',
            'bulk_effective_until.date' => 'Tanggal selesai berlaku tidak valid.',
            'bulk_effective_until.after_or_equal' => 'Tanggal selesai berlaku tidak boleh sebelum tanggal mulai.',
            'bulk_employee_niks.max' => 'Daftar NIK terlalu panjang. Bagi assignment menjadi beberapa batch.',
            'bulk_assignment_mode.in' => 'Mode assignment lokasi tidak valid.',
            'confirm_bulk_assignment.accepted' => 'Konfirmasi assign massal wajib dicentang sebelum menyimpan.',
        ];
    }

    public function withValidator($validator): void
    {
        self::validateFilterPresence($validator);
    }

    public static function validateFilterPresence($validator): void
    {
        $validator->after(function ($validator) {
            $data = $validator->getData();

            if (
                blank(trim((string) ($data['bulk_employee_niks'] ?? '')))
                &&
                blank($data['bulk_perusahaan_id'] ?? null)
                && blank($data['bulk_departemen_id'] ?? null)
                && blank($data['bulk_divisi_id'] ?? null)
            ) {
                $validator->errors()->add('bulk_filter', 'Pilih minimal satu filter area/departemen/divisi atau isi daftar NIK spesifik agar assignment massal tidak mengenai seluruh karyawan tanpa sengaja.');
            }
        });
    }
}
