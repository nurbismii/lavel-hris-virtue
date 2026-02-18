<?php

namespace App\Imports;

use App\Models\Resign;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;

class ImportResign implements
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

        // Ambil semua NIK dalam 1 chunk
        $niks = $collection->pluck('nik_karyawan')->filter()->toArray();

        // Ambil NIK yang sudah ada di database
        $existingNiks = Resign::whereIn('nik_karyawan', $niks)
            ->pluck('nik_karyawan')
            ->toArray();

        foreach ($collection as $collect) {

            if (in_array($collect['nik_karyawan'], $existingNiks)) {
                continue;
            }

            $datas[] = [
                'no_surat' => $collect['no_surat'] ?? null,
                'nik_karyawan' => $collect['nik_karyawan'],
                'no_ktp' => $collect['no_ktp'] ?? null,
                'tanggal_pengajuan' => $this->parseDate($collect['tanggal_pengajuan'] ?? null),
                'tanggal_keluar' => $this->parseDate($collect['tanggal_keluar'] ?? null),
                'alasan_keluar' => $collect['alasan_keluar'] ?? null,
                'tipe' => strtoupper($collect['tipe'] ?? ''),
                'periode_awal' => $this->parseDate($collect['periode_awal'] ?? null),
                'periode_akhir' => $this->parseDate($collect['periode_akhir'] ?? null),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($datas)) {
            Resign::upsert(
                $datas,
                ['nik_karyawan'], // unique key
                array_keys($datas[0]) // kolom yang diupdate
            );
        }
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function batchSize(): int
    {
        return 300;
    }

    public function rules(): array
    {
        return [
            'nik_karyawan' => 'required',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'nik_karyawan.required' => 'NIK Karyawan wajib diisi',
        ];
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
