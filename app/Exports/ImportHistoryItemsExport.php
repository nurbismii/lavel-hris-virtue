<?php

namespace App\Exports;

use App\Models\ImportHistory;
use App\Models\ImportHistoryItem;
use Generator;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ImportHistoryItemsExport implements FromGenerator, WithHeadings, WithMapping, ShouldAutoSize
{
    private ImportHistory $history;
    private string $category;
    private array $payloadKeys;
    private bool $itemsTableReady;

    public function __construct(ImportHistory $history, string $category)
    {
        $this->history = $history;
        $this->category = $category;
        $this->itemsTableReady = Schema::hasTable('import_history_items');
        $firstItem = $this->itemsTableReady ? $this->itemsQuery()->first() : null;
        $payloadKeys = array_keys($firstItem && is_array($firstItem->payload) ? $firstItem->payload : []);
        $this->payloadKeys = array_values(array_diff($payloadKeys, [
            'nama_karyawan',
            'employee_name',
            'nama',
        ]));
    }

    public function generator(): Generator
    {
        $number = 0;

        if ($this->itemsTableReady) {
            foreach ($this->itemsQuery()->cursor() as $item) {
                $item->export_number = ++$number;
                yield $item;
            }
        }
    }

    public function headings(): array
    {
        return array_merge(
            ['No', 'Baris Excel', 'NIK/Identitas', 'Nama Karyawan', 'File', 'Keterangan'],
            array_map(function ($key) {
                return ucwords(str_replace('_', ' ', (string) $key));
            }, $this->payloadKeys)
        );
    }

    public function map($item): array
    {
        $payload = is_array($item->payload) ? $item->payload : [];
        $values = array_map(function ($key) use ($payload) {
            $value = $payload[$key] ?? null;

            if (is_bool($value)) {
                return $value ? 'Ya' : 'Tidak';
            }

            return is_scalar($value) || $value === null
                ? $value
                : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $this->payloadKeys);

        return array_map([$this, 'excelSafe'], array_merge([
            $item->export_number,
            $item->row_number,
            $item->nik,
            $this->employeeName($item, $payload),
            $item->file_name,
            $item->message,
        ], $values));
    }

    private function itemsQuery()
    {
        return ImportHistoryItem::query()
            ->where('import_history_id', $this->history->id)
            ->where('category', $this->category)
            ->orderBy('id');
    }

    private function employeeName($item, array $payload): ?string
    {
        return $item->employee_name
            ?? $payload['nama_karyawan']
            ?? $payload['employee_name']
            ?? $payload['nama']
            ?? null;
    }

    private function excelSafe($value)
    {
        if (is_string($value) && preg_match('/^[=+\-@]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }
}
