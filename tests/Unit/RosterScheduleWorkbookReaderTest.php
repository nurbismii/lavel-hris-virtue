<?php

namespace Tests\Unit;

use App\Services\Roster\RosterScheduleWorkbookReader;
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class RosterScheduleWorkbookReaderTest extends TestCase
{
    /** @var array<int, string> */
    private array $fixturePaths = [];

    protected function tearDown(): void
    {
        foreach ($this->fixturePaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_reader_normalizes_headers_identifiers_dates_and_remarks(): void
    {
        $path = $this->workbookPath(function (Spreadsheet $spreadsheet): void {
            $sheet = $spreadsheet->getActiveSheet();
            $this->writeHeaders($sheet);
            $sheet->setCellValueExplicit('B3', '016090940', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C3', '7402243101930001', DataType::TYPE_STRING);
            $sheet->setCellValue('D3', 'Nama Excel');
            $sheet->setCellValue('E3', ExcelDate::PHPToExcel(new DateTimeImmutable('2026-09-10')));
            $sheet->setCellValue('F3', 'I. AMBIL CUTI');
            $sheet->setCellValue('G3', '2026-10-01');
        });

        $data = app(RosterScheduleWorkbookReader::class)->read($path);
        $row = $data->rows->first();

        $this->assertSame('Roster', $data->sheetName);
        $this->assertSame('016090940', $row['nik']);
        $this->assertSame('7402243101930001', $row['no_ktp']);
        $this->assertNull($row['identity_error']);
        $this->assertSame('2026-09-10', $row['periods'][0]['off_start']);
        $this->assertSame('I. AMBIL CUTI', $row['periods'][0]['raw_remark']);
        $this->assertSame('E', $row['periods'][0]['source_column']);
        $this->assertSame('F', $row['periods'][0]['remark_column']);
        $this->assertCount(1, $data->columns);
    }

    public function test_reader_marks_numeric_and_non_digit_identity_values_without_losing_text_identifiers(): void
    {
        $numericKtpPath = $this->workbookPath(function (Spreadsheet $spreadsheet): void {
            $sheet = $spreadsheet->getActiveSheet();
            $this->writeHeaders($sheet);
            $sheet->setCellValueExplicit('B3', '000123456', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C3', 7402243101930001, DataType::TYPE_NUMERIC);
            $sheet->setCellValue('D3', 'Numeric KTP');
        });

        $malformedIdentityPath = $this->workbookPath(function (Spreadsheet $spreadsheet): void {
            $sheet = $spreadsheet->getActiveSheet();
            $this->writeHeaders($sheet);
            $sheet->setCellValueExplicit('B3', '000123456', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C3', '74A224', DataType::TYPE_STRING);
            $sheet->setCellValue('D3', 'Malformed KTP');
        });

        $numericRow = app(RosterScheduleWorkbookReader::class)->read($numericKtpPath)->rows->first();
        $malformedRow = app(RosterScheduleWorkbookReader::class)->read($malformedIdentityPath)->rows->first();

        $this->assertSame('unsafe_numeric_identity', $numericRow['identity_error']);
        $this->assertSame('non_digit_identity', $malformedRow['identity_error']);
    }

    public function test_reader_normalizes_formula_dates_and_keeps_empty_periods_without_inventing_dates(): void
    {
        $path = $this->workbookPath(function (Spreadsheet $spreadsheet): void {
            $sheet = $spreadsheet->getActiveSheet();
            $this->writeHeaders($sheet);
            $sheet->setCellValueExplicit('B3', '000123456', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C3', '7402243101930001', DataType::TYPE_STRING);
            $sheet->setCellValue('D3', 'Formula Date');
            $sheet->setCellValue('E3', '=DATE(2026,9,11)');

            $sheet->setCellValueExplicit('B4', '000123457', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C4', '7402243101930002', DataType::TYPE_STRING);
            $sheet->setCellValue('D4', 'Empty Period');
        });

        $rows = app(RosterScheduleWorkbookReader::class)->read($path)->rows->values();

        $this->assertSame('2026-09-11', $rows[0]['periods'][0]['off_start']);
        $this->assertNull($rows[0]['periods'][0]['cell_error']);
        $this->assertNull($rows[1]['periods'][0]['off_start']);
        $this->assertNull($rows[1]['periods'][0]['cell_error']);
    }

    public function test_reader_preserves_full_remarks_and_pairs_each_period_with_its_year_remark_column(): void
    {
        $longRemark = 'I. ' . str_repeat('CATATAN ROSTER ', 250);
        $path = $this->workbookPath(function (Spreadsheet $spreadsheet) use ($longRemark): void {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray([
                ['No', 'NIK', 'No KTP', 'Nama Karyawan', 2026, 2026, 2027, 2027, 'Lookup Resign'],
                ['', '', '', '', 'I', 'REMARKS', 'II', 'REMARKS', 'Status'],
            ]);
            $sheet->setCellValueExplicit('B3', '000123456', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C3', '7402243101930001', DataType::TYPE_STRING);
            $sheet->setCellValue('D3', 'Multiple Years');
            $sheet->setCellValue('E3', '2026-09-10');
            $sheet->setCellValue('F3', $longRemark);
            $sheet->setCellValue('G3', '2027-02-12');
            $sheet->setCellValue('H3', 'II. AMBIL INSENTIF');

            $sheet->setCellValueExplicit('B5', '000123457', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C5', '7402243101930002', DataType::TYPE_STRING);
            $sheet->setCellValue('D5', 'After Blank Row');
        });

        $data = app(RosterScheduleWorkbookReader::class)->read($path);
        $rows = $data->rows->values();

        $this->assertCount(2, $data->columns);
        $this->assertSame(2026, $rows[0]['periods'][0]['year']);
        $this->assertSame('E', $rows[0]['periods'][0]['source_column']);
        $this->assertSame('F', $rows[0]['periods'][0]['remark_column']);
        $this->assertSame($longRemark, $rows[0]['periods'][0]['raw_remark']);
        $this->assertSame(2027, $rows[0]['periods'][1]['year']);
        $this->assertSame('G', $rows[0]['periods'][1]['source_column']);
        $this->assertSame('H', $rows[0]['periods'][1]['remark_column']);
        $this->assertSame('II. AMBIL INSENTIF', $rows[0]['periods'][1]['raw_remark']);
        $this->assertSame([3, 5], $rows->pluck('row_number')->all());
    }

    public function test_reader_uses_safe_stable_errors_for_formula_failures_and_invalid_dates(): void
    {
        $path = $this->workbookPath(function (Spreadsheet $spreadsheet): void {
            $sheet = $spreadsheet->getActiveSheet();
            $this->writeHeaders($sheet);
            $sheet->setCellValueExplicit('B3', '000123456', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C3', '7402243101930001', DataType::TYPE_STRING);
            $sheet->setCellValue('D3', 'Formula Failure');
            $sheet->setCellValue('E3', '=1/0');

            $sheet->setCellValueExplicit('B4', '000123457', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C4', '7402243101930002', DataType::TYPE_STRING);
            $sheet->setCellValue('D4', 'Invalid Date');
            $sheet->setCellValue('E4', 'bukan tanggal');
        });

        $rows = app(RosterScheduleWorkbookReader::class)->read($path)->rows->values();

        $this->assertNull($rows[0]['periods'][0]['off_start']);
        $this->assertSame('date_calculation_failed', $rows[0]['periods'][0]['cell_error']);
        $this->assertNull($rows[1]['periods'][0]['off_start']);
        $this->assertSame('invalid_date', $rows[1]['periods'][0]['cell_error']);
        $this->assertStringNotContainsString('7402243101930001', $rows[0]['periods'][0]['cell_error']);
        $this->assertStringNotContainsString('7402243101930002', $rows[1]['periods'][0]['cell_error']);
    }

    /** @param callable(Spreadsheet): void $configure */
    private function workbookPath(callable $configure): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->setTitle('Roster');
        $configure($spreadsheet);

        $path = tempnam(sys_get_temp_dir(), 'roster-workbook-');
        $this->assertNotFalse($path);
        @unlink($path);
        $path .= '.xlsx';
        $this->fixturePaths[] = $path;

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function writeHeaders($sheet): void
    {
        $sheet->fromArray([
            ['No', 'NIK', 'No KTP', 'Nama Karyawan', 2026, 2026, 'Lookup Resign'],
            ['', '', '', '', 'I', 'REMARKS', 'Status'],
        ]);
    }
}
