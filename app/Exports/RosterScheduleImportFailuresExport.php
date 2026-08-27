<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

final class RosterScheduleImportFailuresExport implements FromArray, WithHeadings
{
    public function __construct(private array $rows)
    {
    }

    public function headings(): array
    {
        return ['No', 'Baris Excel', 'NIK', 'Nomor KTP', 'Nama Excel', 'Nama HRIS', 'Tahun', 'Periode', 'Kolom', 'Nilai Bermasalah', 'Jenis Kegagalan', 'Alasan', 'Saran Perbaikan'];
    }

    public function array(): array
    {
        return array_map(function (array $row, int $index): array {
            $error = $row['errors'][0] ?? [];

            return [$index + 1, $row['row_number'], $this->safe($row['nik']), $this->safe($row['no_ktp']), $this->safe($row['employee_name']), $this->safe($row['hris_name']), $row['year'], $row['period_number'], $error['column'] ?? null, $this->safe($row['off_start']), $error['code'] ?? null, $this->safe($error['reason'] ?? null), 'Perbaiki data lalu unggah kembali'];
        }, $this->rows, array_keys($this->rows));
    }

    private function safe($value)
    {
        return is_string($value) && preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
    }
}
