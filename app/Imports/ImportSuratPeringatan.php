<?php

namespace App\Imports;

use App\Imports\Concerns\TracksImportHistory;
use App\Models\Employee;
use App\Models\SuratPeringatan;
use App\Models\ImportHistoryItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\RemembersChunkOffset;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
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
    WithEvents,
    ShouldQueue
{
    use RegistersEventListeners;
    use RemembersChunkOffset;
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
        $detailItems = [];

        // Ambil kombinasi nik + no_sp dari file
        $pairs = $collection->map(function ($row) {
            return [
                'nik_karyawan' => (string) ($row['nik'] ?? ''),
                'no_sp' => (string) ($row['no_sp'] ?? ''),
            ];
        })->filter(function ($pair) {
            return $pair['nik_karyawan'] !== '' && $pair['no_sp'] !== '';
        })->values();

        // Ambil kombinasi yang sudah ada di database
        $existing = [];

        if ($pairs->isNotEmpty()) {
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
        }

        $employeeNames = Employee::query()
            ->whereIn('nik', $pairs->pluck('nik_karyawan')->unique()->values()->all())
            ->pluck('nama_karyawan', 'nik')
            ->mapWithKeys(function ($name, $nik) {
                return [(string) $nik => $name];
            });

        foreach ($collection as $index => $collect) {

            $key = $collect['nik'] . '-' . $collect['no_sp'];

            if (in_array($key, $existing, true)) {
                $skippedCount++;

                if (count($failureSamples) < 10) {
                    $failureSamples[] = [
                        'status' => 'skip',
                        'nik' => (string) ($collect['nik'] ?? ''),
                        'message' => "Pelanggaran {$key} sudah ada.",
                    ];
                }

                $detailItems[] = [
                    'category' => ImportHistoryItem::CATEGORY_SKIPPED,
                    'row' => (int) (isset($this->chunkOffset) ? $this->chunkOffset : 2) + (int) $index,
                    'nik' => (string) ($collect['nik'] ?? ''),
                    'employee_name' => $employeeNames->get((string) ($collect['nik'] ?? '')),
                    'message' => "Pelanggaran {$key} sudah ada.",
                    'payload' => method_exists($collect, 'toArray') ? $collect->toArray() : (array) $collect,
                ];

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

        if (!empty($datas)) {
            SuratPeringatan::upsert(
                $datas,
                ['nik_karyawan', 'no_sp'], // unique combination
                ['level_sp', 'tgl_mulai', 'tgl_berakhir', 'keterangan', 'pelapor']
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
            0,
            $detailItems
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
