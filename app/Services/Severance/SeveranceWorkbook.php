<?php

namespace App\Services\Severance;

use Generator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

class SeveranceWorkbook
{
    public const HEADINGS = ['NO', 'NIK', 'NAMA', 'ENTRY DATE', 'MASA KERJA', 'STATUS', 'JABATAN',
        'ALAMAT DESA', 'ALAMAT KECAMATAN', 'ALAMAT KABUPATEN', 'ALAMAT PROVINSI', 'PELANGGARAN/ALASAN',
        'ONGKOS PULANG', 'GAJI POKOK', 'TUNJ MASA KERJA', 'TUNJ JABATAN', 'SISA CUTI', 'PASAL', 'NO HP', 'USIA'];

    public function chunks(string $path): Generator
    {
        $reader = new Xlsx();
        $reader->setReadDataOnly(true)->setReadEmptyCells(false);
        $info = $reader->listWorksheetInfo($path);
        if (count($info) !== 1 || $info[0]['totalRows'] > (int) config('severance.max_rows') + 7 || $info[0]['totalColumns'] > 20) {
            $this->invalid('Gunakan satu sheet, header di baris 7, maksimal 10.000 baris data sesuai template.');
        }
        $reader->setLoadSheetsOnly($info[0]['worksheetName']);
        $seen = [];
        for ($start = 8; $start <= max(8, $info[0]['totalRows']); $start += 200) {
            $reader->setReadFilter(new class($start) implements IReadFilter {
                private $start;
                public function __construct(int $start) { $this->start = $start; }
                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    return ($row === 7 && in_array($columnAddress, ['B', 'L', 'N', 'O', 'P', 'Q', 'R'], true))
                        || ($row >= $this->start && $row < $this->start + 200 && in_array($columnAddress, ['B', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'R', 'S', 'T'], true));
                }
            });
            $book = $reader->load($path);
            try {
                $sheet = $book->getSheet(0);
                foreach (['B' => 'NIK', 'L' => 'PELANGGARAN/ALASAN', 'N' => 'GAJI POKOK', 'O' => 'TUNJ MASA KERJA',
                    'P' => 'TUNJ JABATAN', 'Q' => 'SISA CUTI', 'R' => 'PASAL'] as $col => $heading) {
                    if (strtoupper(trim((string) $sheet->getCell($col . '7')->getValue())) !== $heading) {
                        $this->invalid('Header baris 7 tidak sesuai file pengajuan. Unduh template yang tersedia.');
                    }
                }
                $rows = [];
                for ($number = $start; $number <= min($start + 199, $info[0]['totalRows']); $number++) {
                    $values = []; $error = null;
                    foreach (['B' => 'nik', 'L' => 'reason', 'M' => 'return_fare', 'R' => 'article'] as $col => $key) {
                        $cell = $sheet->getCell($col . $number);
                        if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                            $error = 'NIK, alasan, ongkos pulang, dan pasal harus berupa nilai, bukan formula. Gunakan Paste Values.';
                        }
                        $value = $cell->getValue();
                        $values[$key] = trim((string) ($value instanceof RichText ? $value->getPlainText() : $value));
                    }
                    if (!array_filter($values, function ($v) { return $v !== ''; })) { continue; }
                    $values['article'] = preg_replace('/\s+/', '', str_ireplace(['pasal', 'ayat'], '', $values['article']));
                    $values['duplicate'] = isset($seen['nik:' . $values['nik']]);
                    $seen['nik:' . $values['nik']] = true;
                    $values['error'] = $error;
                    $values['metadata'] = [];
                    foreach (['F', 'G', 'H', 'I', 'J', 'K', 'S', 'T'] as $col) {
                        $cell = $sheet->getCell($col . $number);
                        // Hanya salin cache biodata pelengkap; jangan jalankan formula/link eksternal.
                        $value = $cell->getDataType() === DataType::TYPE_FORMULA ? $cell->getOldCalculatedValue() : $cell->getValue();
                        if ($value instanceof RichText) { $value = $value->getPlainText(); }
                        $values['metadata'][$col] = is_scalar($value) ? mb_substr((string) $value, 0, 250) : '';
                    }
                    $rows[$number] = $values;
                }
            } finally { $book->disconnectWorksheets(); }
            yield $rows;
        }
    }

    private function invalid(string $message): void
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
