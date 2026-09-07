<?php

namespace App\Exports;

use App\Models\CvMakerProgressStatus;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class CvMakerProgressExport extends StringValueBinder implements FromGenerator, WithHeadings, WithCustomValueBinder, WithColumnWidths
{
    private $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function headings(): array
    {
        return ['NIK', 'Nama Karyawan', 'Perusahaan', 'Departemen', 'Divisi', 'Posisi HRIS',
            'Status Karyawan', 'Status Progress CV', 'Tahap Saat Ini', 'Tahap Selesai',
            'Total Tahap', 'Sinkronisasi Terakhir'];
    }

    public function columnWidths(): array
    {
        return ['A' => 22, 'B' => 32, 'C' => 16, 'D' => 30, 'E' => 30, 'F' => 40,
            'G' => 22, 'H' => 54, 'I' => 24, 'J' => 18, 'K' => 16, 'L' => 24];
    }

    public function generator(): \Generator
    {
        $query = clone $this->query;
        $query->setEagerLoads([]);
        $query->select(['employees.nik', 'employees.nama_karyawan', 'employees.area_kerja',
            'employees.departemen_id', 'employees.divisi_id', 'employees.posisi', 'employees.status_resign'])
            ->with(['departemen:id,departemen', 'divisi:id,nama_divisi'])->reorder();

        foreach ($query->lazyById(250, 'employees.nik', 'nik')->chunk(250) as $employees) {
            $statuses = CvMakerProgressStatus::query()->whereIn('employee_nik', $employees->pluck('nik'))
                ->get()->keyBy('employee_nik');
            foreach ($employees as $employee) {
                $status = $statuses->get($employee->nik);
                yield [$employee->nik, $employee->nama_karyawan, $employee->area_kerja,
                    optional($employee->departemen)->departemen, optional($employee->divisi)->nama_divisi,
                    $employee->posisi, $employee->status_resign, self::statusLabel($status),
                    $status ? $status->current_step_label : null,
                    $status ? $status->completed_step_count : null,
                    $status ? $status->total_step_count : null,
                    $status && $status->last_synced_at ? $status->last_synced_at->format('Y-m-d H:i:s') : null];
            }
        }
    }

    public static function statusLabel(?CvMakerProgressStatus $status): string
    {
        if (!$status) {
            return 'Snapshot belum tersedia (status belum diketahui)';
        }
        if (!$status->cv_user_id) {
            return 'Belum memiliki akun CV';
        }
        if (!$status->cv_profile_id) {
            return 'Profil CV belum dibuat';
        }
        return $status->is_complete ? 'Sudah lengkap' : 'Dalam progress / belum lengkap';
    }
}
