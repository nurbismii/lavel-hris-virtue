<?php

namespace App\Http\Requests\Presensi;

use App\Services\Presensi\AttendanceAnomalyService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceAnomalyFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            && $this->user()->hasMenuAccess('attendance_anomaly')
            && $this->user()->hasRole(['Super Admin', 'HR']);
    }

    public function rules(): array
    {
        $anomalyKeys = array_keys(app(AttendanceAnomalyService::class)->anomalyTypes());
        $anomalyKeys[] = 'all';

        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'area' => ['nullable'],
            'area.*' => ['nullable', 'string', 'max:20'],
            'departemen_id' => ['nullable', 'integer'],
            'divisi_id' => ['nullable', 'integer'],
            'anomaly' => ['nullable', Rule::in($anomalyKeys)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $dateFrom = $this->input('date_from');
            $dateTo = $this->input('date_to');

            if (!$dateFrom || !$dateTo) {
                return;
            }

            if ((int) Carbon::parse($dateFrom)->diffInDays(Carbon::parse($dateTo), true) > 62) {
                $validator->errors()->add('date_to', 'Rentang tanggal maksimal 62 hari agar query tetap ringan.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'date_from.date' => 'Tanggal awal tidak valid.',
            'date_to.date' => 'Tanggal akhir tidak valid.',
            'date_to.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal awal.',
            'date_to.max_range' => 'Rentang tanggal terlalu panjang.',
            'departemen_id.integer' => 'Departemen tidak valid.',
            'divisi_id.integer' => 'Divisi tidak valid.',
            'anomaly.in' => 'Jenis anomali tidak valid.',
        ];
    }
}
