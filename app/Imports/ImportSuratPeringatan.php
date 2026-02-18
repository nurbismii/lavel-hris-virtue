<?php

namespace App\Imports;

use App\Models\SuratPeringatan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;

class ImportSuratPeringatan implements
    ToCollection,
    WithHeadingRow,
    WithValidation,
    WithChunkReading,
    WithBatchInserts,
    ShouldQueue
{
    public function collection(Collection $collection)
    {
        $datas = [];

        // Ambil kombinasi nik + no_sp dari file
        $pairs = $collection->map(function ($row) {
            return [
                'nik_karyawan' => $row['nik'],
                'no_sp' => $row['no_sp'],
            ];
        });

        // Ambil kombinasi yang sudah ada di database
        $existing = SuratPeringatan::where(function ($query) use ($pairs) {
            foreach ($pairs as $pair) {
                $query->orWhere(function ($q) use ($pair) {
                    $q->where('nik_karyawan', $pair['nik_karyawan'])
                        ->where('no_sp', $pair['no_sp']);
                });
            }
        })->get(['nik_karyawan', 'no_sp'])
            ->map(function ($item) {
                return $item->nik_karyawan . '-' . $item->no_sp;
            })
            ->toArray();

        foreach ($collection as $collect) {

            $key = $collect['nik'] . '-' . $collect['no_sp'];

            if (in_array($key, $existing)) {
                continue;
            }

            $datas[] = [
                'nik_karyawan' => $collect['nik'],
                'no_sp' => $collect['no_sp'],
                'level_sp' => $collect['level_sp'],
                'tgl_mulai' => $this->parseDate($collect['tgl_mulai']),
                'tgl_berakhir' => $this->parseDate($collect['tgl_berakhir']),
                'keterangan' => $collect['keterangan'],
                'pelapor' => $collect['pelapor'],
                'created_at' => now()
            ];
        }

        SuratPeringatan::upsert(
            $datas,
            ['nik_karyawan', 'no_sp'], // unique combination
            ['level_sp', 'tgl_mulai', 'tgl_berakhir', 'keterangan', 'pelapor']
        );
    }

    public function rules(): array
    {
        return [
            'nik' => 'required',
            'no_sp' => 'required'
        ];
    }

    public function customValidationMessages()
    {
        return [
            'nik.required' => 'NIK karyawan harus wajib diisi',
            'no_sp.required' => 'Nomor SP harus diisi',
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function batchSize(): int
    {
        return 300;
    }

    private function parseDate($value)
    {
        try {
            return Carbon::instance(
                \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject(intval($value))
            );
        } catch (\Throwable $th) {
            return null;
        }
    }
}
