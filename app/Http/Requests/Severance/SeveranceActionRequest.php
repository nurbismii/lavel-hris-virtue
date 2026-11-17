<?php

namespace App\Http\Requests\Severance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SeveranceActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasMenuAccess(['severance_staff', 'severance_payroll', 'severance_hi']);
    }

    public function rules(): array
    {
        $rules = ['revision' => ['required', 'integer', 'min:1']];
        if ($this->routeIs('severance.update')) {
            $rules += [
                'article' => ['required', Rule::in(array_keys(config('severance.articles')))],
                'reason' => ['required', 'string', 'max:2000'],
                'calculation_start' => ['required', 'date_format:Y-m-d', 'before_or_equal:termination_date'],
                'termination_date' => ['required', 'date_format:Y-m-d'],
                'return_fare' => ['required', 'numeric', 'min:0', 'max:999999999', 'regex:/^\d+(\.\d{1,2})?$/']
            ];
        } elseif ($this->routeIs('severance.payroll')) {
            $rules += ['source_hash' => ['required', 'string', 'size:64'], 'reviewed' => ['accepted']];
        } elseif ($this->routeIs('severance.hi')) {
            $rules += [
                'intent' => ['required', Rule::in(['preview', 'approve'])],
                'leave_days' => ['required', 'numeric', 'min:0', 'max:366', 'regex:/^\d+(\.\d{1,2})?$/'],
                'reviewed' => ['required_if:intent,approve', 'accepted_if:intent,approve'],
                'preview_hash' => ['required_if:intent,approve', 'nullable', 'string', 'size:64']
            ];
        } elseif ($this->routeIs('severance.reject')) {
            $rules += ['note' => ['required', 'string', 'max:500']];
        }
        return $rules;
    }

    public function attributes(): array
    {
        return [
            'article' => 'pasal',
            'reason' => 'alasan',
            'calculation_start' => 'awal perhitungan',
            'termination_date' => 'tanggal akhir kerja',
            'return_fare' => 'ongkos pulang',
            'leave_days' => 'sisa cuti',
            'reviewed' => 'konfirmasi pemeriksaan',
            'note' => 'alasan pengembalian'
        ];
    }

    public function messages(): array
    {
        return [
            'leave_days.required' => 'Supervisor HI wajib mengisi sisa cuti; isi 0 bila tidak ada.',
            'preview_hash.required_if' => 'Tampilkan dan periksa pratinjau sebelum menyetujui.',
            'reviewed.accepted' => 'Konfirmasikan bahwa data telah diperiksa.',
            'reviewed.accepted_if' => 'Konfirmasikan bahwa data telah diperiksa.'
        ];
    }
}
