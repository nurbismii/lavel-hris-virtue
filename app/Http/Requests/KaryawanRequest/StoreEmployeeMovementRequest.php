<?php

namespace App\Http\Requests\KaryawanRequest;

use App\Models\Divisi;
use App\Models\Employee;
use App\Models\EmployeeMovement;
use App\Services\Karyawan\EmployeeMovementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeMovementRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()
            && app(EmployeeMovementService::class)->canAccessMovementModule($this->user());
    }

    public function rules()
    {
        return [
            'employee_nik' => ['required', 'string', 'max:32', 'exists:employees,nik'],
            'movement_type' => ['required', Rule::in(array_keys(EmployeeMovement::typeOptions()))],
            'effective_date' => ['required', 'date', 'before_or_equal:today'],
            'new_posisi' => ['nullable', 'string', 'max:255'],
            'new_jabatan' => ['nullable', 'string', 'max:255'],
            'new_departemen_id' => ['nullable', 'integer', 'exists:departemens,id'],
            'new_divisi_id' => ['nullable', 'integer', 'exists:divisis,id'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $employee = Employee::query()
                ->where('nik', $this->input('employee_nik'))
                ->first();

            if (!$employee || !app(EmployeeMovementService::class)->canSubmitForEmployee($employee, $this->user())) {
                $validator->errors()->add('employee_nik', 'Karyawan tidak tersedia dalam scope akses Anda.');
                return;
            }

            if ($employee->status_resign !== 'AKTIF') {
                $validator->errors()->add('employee_nik', 'Promosi, demosi, atau mutasi hanya dapat dilakukan untuk karyawan aktif.');
            }

            $movementType = $this->input('movement_type');

            if (in_array($movementType, [EmployeeMovement::TYPE_PROMOTION, EmployeeMovement::TYPE_DEMOTION], true)) {
                $this->validatePositionMovement($validator, $employee);
                return;
            }

            if ($movementType === EmployeeMovement::TYPE_MUTATION) {
                $this->validateMutation($validator, $employee);
            }
        });
    }

    public function messages()
    {
        return [
            'employee_nik.required' => 'Karyawan wajib dipilih.',
            'employee_nik.exists' => 'Karyawan tidak ditemukan.',
            'movement_type.required' => 'Jenis pergerakan wajib dipilih.',
            'movement_type.in' => 'Jenis pergerakan tidak valid.',
            'effective_date.required' => 'Tanggal efektif wajib diisi.',
            'effective_date.before_or_equal' => 'Tanggal efektif tidak boleh lebih dari hari ini karena approval HRD final langsung menerapkan perubahan ke master karyawan.',
            'new_posisi.max' => 'Posisi baru maksimal 255 karakter.',
            'new_jabatan.max' => 'Jabatan baru maksimal 255 karakter.',
            'new_departemen_id.exists' => 'Departemen tujuan tidak ditemukan.',
            'new_divisi_id.exists' => 'Divisi tujuan tidak ditemukan.',
            'reference_number.max' => 'Nomor referensi maksimal 120 karakter.',
            'reason.required' => 'Alasan atau dasar perubahan wajib diisi.',
            'reason.min' => 'Alasan minimal 5 karakter.',
            'reason.max' => 'Alasan maksimal 1000 karakter.',
        ];
    }

    private function validatePositionMovement($validator, Employee $employee): void
    {
        $newPosition = $this->cleanText($this->input('new_posisi'));
        $newJabatan = $this->cleanText($this->input('new_jabatan'));
        $currentPosition = $this->cleanText($employee->posisi);
        $currentJabatan = $this->cleanText($employee->jabatan);

        if (!$newPosition) {
            $validator->errors()->add('new_posisi', 'Posisi baru wajib diisi untuk promosi atau demosi.');
            return;
        }

        if (
            $newPosition === $currentPosition
            && (!$newJabatan || $newJabatan === $currentJabatan)
        ) {
            $validator->errors()->add('new_posisi', 'Posisi atau jabatan baru harus berbeda dari data saat ini.');
        }

        if ($this->filled('new_departemen_id') || $this->filled('new_divisi_id')) {
            $validator->errors()->add('new_departemen_id', 'Perubahan departemen/divisi dicatat melalui jenis Mutasi.');
        }
    }

    private function validateMutation($validator, Employee $employee): void
    {
        if ($this->filled('new_posisi') || $this->filled('new_jabatan')) {
            $validator->errors()->add('new_posisi', 'Perubahan posisi dicatat melalui jenis Promosi atau Demosi.');
        }

        if (!$this->filled('new_departemen_id') && !$this->filled('new_divisi_id')) {
            $validator->errors()->add('new_departemen_id', 'Departemen atau divisi tujuan wajib dipilih untuk mutasi.');
            return;
        }

        $targetDepartemenId = $this->filled('new_departemen_id') ? (int) $this->input('new_departemen_id') : null;
        $targetDivisiId = $this->filled('new_divisi_id') ? (int) $this->input('new_divisi_id') : null;

        if ($targetDivisiId) {
            $division = Divisi::query()->select('id', 'departemen_id')->find($targetDivisiId);

            if (!$division) {
                return;
            }

            if ($targetDepartemenId && (int) $division->departemen_id !== $targetDepartemenId) {
                $validator->errors()->add('new_divisi_id', 'Divisi tujuan tidak sesuai dengan departemen tujuan.');
                return;
            }

            $targetDepartemenId = $division->departemen_id ? (int) $division->departemen_id : $targetDepartemenId;
        }

        $currentDepartemenId = $employee->departemen_id ? (int) $employee->departemen_id : null;
        $currentDivisiId = $employee->divisi_id ? (int) $employee->divisi_id : null;

        if ($targetDepartemenId === $currentDepartemenId && $targetDivisiId === $currentDivisiId) {
            $validator->errors()->add('new_departemen_id', 'Departemen atau divisi tujuan harus berbeda dari penempatan saat ini.');
        }
    }

    private function cleanText($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
