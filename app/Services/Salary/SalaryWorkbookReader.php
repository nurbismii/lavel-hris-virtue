<?php

namespace App\Services\Salary;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Illuminate\Validation\ValidationException;

class SalaryWorkbookReader
{
    public const HEADINGS = ['nik', 'gaji_pokok', 'tunjangan_tetap', 'tunjangan_tidak_tetap', 'berlaku_mulai', 'alasan', 'referensi'];

    public function chunks(string $path): \Generator
    {
        $reader = new Xlsx();
        $reader->setReadDataOnly(true);
        $info = $reader->listWorksheetInfo($path);
        if (count($info) !== 1 || $info[0]['totalRows'] > (int) config('salary.import.max_rows', 10000) + 1
            || $info[0]['totalColumns'] > 20) {
            throw ValidationException::withMessages(['file' => 'Gunakan satu sheet, maksimal 10.000 baris, sesuai template.']);
        }
        $reader->setLoadSheetsOnly($info[0]['worksheetName']);
        for ($start = 2; $start <= max(2, $info[0]['totalRows']); $start += 200) {
            $reader->setReadFilter(new class($start) implements IReadFilter {
                private $start;
                public function __construct(int $start) { $this->start = $start; }
                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    return strlen($columnAddress) === 1 && $columnAddress <= 'G'
                        && ($row === 1 || ($row >= $this->start && $row < $this->start + 200));
                }
            });
            $book = $reader->load($path);
            try {
                $sheet = $book->getSheet(0);
                $headings = [];
                foreach (range('A', 'G') as $column) {
                    $headings[] = strtolower(trim((string) $sheet->getCell($column . '1')->getValue()));
                }
                if ($headings !== self::HEADINGS) {
                    throw ValidationException::withMessages(['file' => 'Header tidak sesuai. Unduh dan gunakan template pembaruan gaji.']);
                }
                $rows = [];
                for ($row = $start; $row <= min($start + 199, $info[0]['totalRows']); $row++) {
                    $values = [];
                    foreach (range('A', 'G') as $column) {
                        $values[] = $sheet->getCell($column . $row)->getValue();
                    }
                    if (count(array_filter($values, function ($value) { return $value !== null && $value !== ''; })) > 0) {
                        $rows[$row] = array_combine(self::HEADINGS, $values);
                    }
                }
            } finally {
                $book->disconnectWorksheets();
            }
            yield $rows;
        }
    }
}
