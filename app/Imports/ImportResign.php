<?php

namespace App\Imports;

use App\Imports\Concerns\TracksImportHistory;
use App\Models\Resign;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
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
    WithEvents,
    ShouldQueue
{
    use RegistersEventListeners;
    use TracksImportHistory;

    public function __construct(?int $importHistoryId = null)
    {
        $this->importHistoryId = $importHistoryId;
    }

    public function collection(Collection $collection)
    {
        $datas = [];
        $skippedCount = 0;
        $failureSamples = [];

        // Ambil semua NIK dalam 1 chunk
        $niks = $collection->pluck('nik_karyawan')
            ->filter()
            ->map(fn($nik) => (string) $nik)
            ->unique()
            ->values()
            ->toArray();

        // Ambil NIK yang sudah ada di database
        $existingNiks = Resign::whereIn('nik_karyawan', $niks)
            ->pluck('nik_karyawan')
            ->map(fn($nik) => (string) $nik)
            ->toArray();

        foreach ($collection as $collect) {

            $nikKaryawan = (string) ($collect['nik_karyawan'] ?? '');

            if (in_array($nikKaryawan, $existingNiks, true)) {
                $skippedCount++;

                if (count($failureSamples) < 10) {
                    $failureSamples[] = [
                        'status' => 'skip',
                        'nik' => $nikKaryawan,
                        'message' => "Data resign untuk NIK {$nikKaryawan} sudah ada.",
                    ];
                }

                continue;
            }

            $datas[] = [
                'no_surat' => $collect['no_surat'] ?? null,
                'nik_karyawan' => $nikKaryawan,
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

        $this->recordImportChunk(
            $collection->count(),
            count($datas),
            $skippedCount,
            0,
            $failureSamples,
            ['chunk_processed_at' => now()->toIso8601String()],
            count($datas),
            0
        );
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
