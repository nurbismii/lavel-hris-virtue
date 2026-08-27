<?php

namespace App\Services\Roster;

use App\Support\Roster\RosterWorkbookData;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

final class RosterScheduleWorkbookReader
{
    private const ROMAN_PERIODS = [
        'I' => 1,
        'II' => 2,
        'III' => 3,
        'IV' => 4,
        'V' => 5,
    ];

    public function read(string $absolutePath): RosterWorkbookData
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            throw new RuntimeException('File workbook roster tidak tersedia atau tidak dapat dibaca.');
        }

        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($absolutePath);

        try {
            $sheet = $spreadsheet->getActiveSheet();
            $columns = $this->scheduleColumns($sheet);
            $rows = $this->rows($sheet, $columns);

            return new RosterWorkbookData($sheet->getTitle(), $columns, $rows);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function scheduleColumns(Worksheet $sheet): array
    {
        $columns = [];
        $remarksByYear = [];
        $currentYear = null;
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        for ($columnIndex = 1; $columnIndex <= $highestColumn; $columnIndex++) {
            $yearValue = $sheet->getCell([$columnIndex, 1])->getValue();

            if (is_numeric($yearValue) && (int) $yearValue >= 2000 && (int) $yearValue <= 2200) {
                $currentYear = (int) $yearValue;
            }

            $periodLabel = mb_strtoupper(trim((string) $sheet->getCell([$columnIndex, 2])->getValue()));

            if ($currentYear !== null && $periodLabel === 'REMARKS') {
                $remarksByYear[$currentYear] = Coordinate::stringFromColumnIndex($columnIndex);
                continue;
            }

            if ($currentYear !== null && isset(self::ROMAN_PERIODS[$periodLabel])) {
                $columns[] = [
                    'year' => $currentYear,
                    'period_number' => self::ROMAN_PERIODS[$periodLabel],
                    'source_column' => Coordinate::stringFromColumnIndex($columnIndex),
                    'remark_column' => null,
                ];
            }
        }

        foreach ($columns as &$column) {
            $column['remark_column'] = $remarksByYear[$column['year']] ?? null;
        }
        unset($column);

        if ($columns === []) {
            throw new RuntimeException('Header tahun dan periode I-V tidak ditemukan pada baris 1-2.');
        }

        return $columns;
    }

    private function rows(Worksheet $sheet, array $columns): Collection
    {
        $rows = collect();
        $highestRow = $sheet->getHighestDataRow();

        for ($rowNumber = 3; $rowNumber <= $highestRow; $rowNumber++) {
            $nik = $this->identifier($sheet->getCell('B' . $rowNumber));
            $ktp = $this->identifier($sheet->getCell('C' . $rowNumber), true);
            $employeeName = trim((string) $sheet->getCell('D' . $rowNumber)->getValue());
            $periods = [];

            foreach ($columns as $column) {
                $date = $this->dateValue($sheet->getCell($column['source_column'] . $rowNumber));
                $periods[] = [
                    'year' => $column['year'],
                    'period_number' => $column['period_number'],
                    'source_column' => $column['source_column'],
                    'remark_column' => $column['remark_column'],
                    'off_start' => $date['value'],
                    'raw_remark' => $this->remarksFor($sheet, $column, $rowNumber),
                    'cell_error' => $date['error'],
                ];
            }

            if (!$this->hasRowData($nik['value'], $ktp['value'], $employeeName, $periods)) {
                continue;
            }

            $rows->push([
                'row_number' => $rowNumber,
                'nik' => $nik['value'],
                'no_ktp' => $ktp['value'],
                'employee_name' => $employeeName,
                'identity_error' => $nik['error'] ?? $ktp['error'],
                'periods' => $periods,
            ]);
        }

        return $rows;
    }

    private function identifier(Cell $cell, bool $isKtp = false): array
    {
        if ($isKtp && $cell->getDataType() === DataType::TYPE_NUMERIC) {
            return [
                'value' => trim((string) $cell->getValue()),
                'error' => 'unsafe_numeric_identity',
            ];
        }

        $value = trim((string) $cell->getValue());

        return [
            'value' => $value,
            'error' => $value === '' || preg_match('/^\d+$/', $value) === 1 ? null : 'non_digit_identity',
        ];
    }

    private function dateValue(Cell $cell): array
    {
        if ($cell->getValue() === null || $cell->getValue() === '') {
            return ['value' => null, 'error' => null];
        }

        try {
            $value = $cell->getCalculatedValue();
        } catch (\Throwable $exception) {
            return ['value' => null, 'error' => 'date_calculation_failed'];
        }

        if ($value === null || $value === '') {
            return ['value' => null, 'error' => null];
        }

        if ($cell->isFormula() && is_string($value) && str_starts_with($value, '#')) {
            return ['value' => null, 'error' => 'date_calculation_failed'];
        }

        try {
            if ($value instanceof DateTimeInterface) {
                return ['value' => Carbon::instance(\DateTime::createFromInterface($value))->toDateString(), 'error' => null];
            }

            if (is_numeric($value)) {
                return [
                    'value' => Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString(),
                    'error' => null,
                ];
            }

            return ['value' => Carbon::parse((string) $value)->toDateString(), 'error' => null];
        } catch (\Throwable $exception) {
            return ['value' => null, 'error' => 'invalid_date'];
        }
    }

    private function remarksFor(Worksheet $sheet, array $column, int $row): ?string
    {
        if ($column['remark_column'] === null) {
            return null;
        }

        $value = (string) $sheet->getCell($column['remark_column'] . $row)->getValue();

        return trim($value) === '' ? null : $value;
    }

    private function hasRowData(string $nik, string $ktp, string $employeeName, array $periods): bool
    {
        if ($nik !== '' || $ktp !== '' || $employeeName !== '') {
            return true;
        }

        foreach ($periods as $period) {
            if ($period['off_start'] !== null || $period['raw_remark'] !== null || $period['cell_error'] !== null) {
                return true;
            }
        }

        return false;
    }
}
