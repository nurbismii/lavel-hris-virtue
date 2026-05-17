<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PkwtOneContractImportTemplateExport implements FromArray, WithTitle, WithStyles, WithColumnWidths, WithColumnFormatting, WithEvents
{
    private const TEMPLATE_LAST_ROW = 1001;
    private const RUPIAH_FORMAT = '_-"Rp"* #,##0_-;\-"Rp"* #,##0_-;_-"Rp"* "-"??_-;_-@_-';
    private const DATE_FORMAT = '[$-421]dd\ mmmm\ yyyy;@';

    public function title(): string
    {
        return 'REGULER';
    }

    public function array(): array
    {
        return [
            [
                'NO',
                'NO. KTP',
                'NAMA',
                'KODE KONTRAK',
                'NO PKWT',
                'JENIS KELAMIN',
                'STATUS PERNIKAHAN',
                'ALAMAT',
                'JABATAN',
                "LAMA \nKONTRAK",
                "TANGGAL MULAI\n KONTRAK",
                'TANGGAL BERAKHIR KONTRAK',
                'GAJI',
                'UANG MAKAN',
                'HM',
                'TUNJANGAN JABATAN',
                'KETERANGAN KONTRAK',
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '000000'],
                    'size' => 10,
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'DFEBF7'],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 3.57,
            'B' => 18.57,
            'C' => 41.14,
            'D' => 9.28,
            'E' => 34.28,
            'F' => 15.43,
            'G' => 15.15,
            'H' => 59.27,
            'I' => 34.28,
            'J' => 9.28,
            'K' => 16.85,
            'L' => 18.57,
            'M' => 12.57,
            'N' => 11.12,
            'O' => 7.48,
            'P' => 13.37,
            'Q' => 14.32,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
            'K' => self::DATE_FORMAT,
            'L' => self::DATE_FORMAT,
            'M' => self::RUPIAH_FORMAT,
            'N' => self::RUPIAH_FORMAT,
            'O' => self::RUPIAH_FORMAT,
            'P' => self::RUPIAH_FORMAT,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->freezePane('F2');
                $sheet->setAutoFilter('A1:Q1');
                $sheet->getRowDimension(1)->setRowHeight(42);
                $sheet->getStyle('A1:Q' . self::TEMPLATE_LAST_ROW)
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A2:Q' . self::TEMPLATE_LAST_ROW)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('H2:I' . self::TEMPLATE_LAST_ROW)
                    ->getAlignment()
                    ->setWrapText(true);
                $sheet->getStyle('B2:B' . self::TEMPLATE_LAST_ROW)
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_TEXT);
                $sheet->getStyle('K2:L' . self::TEMPLATE_LAST_ROW)
                    ->getNumberFormat()
                    ->setFormatCode(self::DATE_FORMAT);
                $sheet->getStyle('M2:P' . self::TEMPLATE_LAST_ROW)
                    ->getNumberFormat()
                    ->setFormatCode(self::RUPIAH_FORMAT);
                $sheet->getStyle('A1:Q' . self::TEMPLATE_LAST_ROW)
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('A2:A' . self::TEMPLATE_LAST_ROW)
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('EBFFDE');
                $sheet->getStyle('E2:E' . self::TEMPLATE_LAST_ROW)
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('F2F2F2');

                for ($row = 2; $row <= self::TEMPLATE_LAST_ROW; $row++) {
                    $sheet->setCellValue('A' . $row, '=IF(C' . $row . '="","",ROW()-1)');
                    $sheet->setCellValue(
                        'E' . $row,
                        '=IF(OR(D' . $row . '="",K' . $row . '=""),"","02-"&D' . $row . '&"/VDNI/HRD/PKWT/"&ROMAN(MONTH(K' . $row . '))&"/"&YEAR(K' . $row . '))'
                    );
                }
            },
        ];
    }
}
