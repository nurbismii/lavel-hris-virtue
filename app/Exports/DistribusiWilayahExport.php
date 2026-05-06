<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DistribusiWilayahExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query;
    }

    public function map($row): array
    {
        return [
            $row->nik,
            $row->nama_karyawan,
            $row->area_kerja,
            $row->jenis_kelamin,
            $row->provinsi,
            $row->kabupaten,
            $row->kecamatan,
            $row->kelurahan,
        ];
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
