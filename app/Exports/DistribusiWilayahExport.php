<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DistribusiWilayahExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    protected $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function collection()
    {
        return $this->rows->map(function ($row) {
            return [
                'nik' => $row->nik,
                'nama_karyawan' => $row->nama_karyawan,
                'area_kerja' => $row->area_kerja,
                'jenis_kelamin' => $row->jenis_kelamin,
                'provinsi' => $row->provinsi,
                'kabupaten' => $row->kabupaten,
                'kecamatan' => $row->kecamatan,
                'kelurahan' => $row->kelurahan,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'NIK',
            'Nama Karyawan',
            'Area Kerja',
            'Jenis Kelamin',
            'Provinsi',
            'Kabupaten',
            'Kecamatan',
            'Kelurahan',
        ];
    }
}
