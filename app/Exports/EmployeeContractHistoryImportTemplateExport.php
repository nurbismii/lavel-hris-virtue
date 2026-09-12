<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class EmployeeContractHistoryImportTemplateExport implements FromArray, WithEvents, WithTitle
{
    public function title(): string
    {
        return 'History Kontrak';
    }

    public function array(): array
    {
        return [[
            'NIK_BARU', 'EMPLOYEE_NAME', 'STATUS_PERNIKAHAN', 'EMPLOYEE_STATUS',
            'NOMOR_KONTRAK', 'ENTRY_DATE', 'HISTORY_URUTAN', 'HISTORY_JENIS',
            'DURASI_BULAN', 'TANGGAL_AKHIR_KONTRAK',
        ]];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $sheet->freezePane('C2');
            $sheet->setAutoFilter('A1:J1');
            $sheet->getRowDimension(1)->setRowHeight(36);
            $sheet->getStyle('A1:J1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
                'alignment' => ['wrapText' => true, 'vertical' => 'center'],
            ]);
            foreach (['A' => 23, 'B' => 34, 'C' => 24, 'D' => 23, 'E' => 42, 'F' => 18, 'G' => 20, 'H' => 22, 'I' => 18, 'J' => 26] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }
            foreach (['A', 'B', 'C', 'D', 'E', 'H'] as $column) {
                $sheet->getStyle($column . '2:' . $column . '1001')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            }
            foreach (['F', 'J'] as $column) {
                $sheet->getStyle($column . '2:' . $column . '1001')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            }
            $notes = [
                'A1' => 'Wajib. NIK karyawan berupa angka, sesuai master karyawan. Format teks mempertahankan nol di depan. Isi data mulai baris 2; jangan ubah header.',
                'B1' => 'Nama karyawan sesuai master data.',
                'C1' => 'Status pernikahan sesuai data karyawan.',
                'D1' => 'Status karyawan sesuai data sumber.',
                'E1' => 'Nomor kontrak asli. Satu baris untuk satu riwayat PKWT atau adendum.',
                'F1' => 'Tanggal masuk (entry date). Gunakan tanggal Excel atau YYYY-MM-DD, misalnya 2026-01-01.',
                'G1' => 'Urutan riwayat per karyawan, misalnya 1, 2, 3. Kombinasi NIK, urutan, nomor kontrak, dan tanggal akhir yang sama akan memperbarui riwayat lama.',
                'H1' => 'Wajib. Pilih PKWT atau ADENDUM. Gunakan ADENDUM saja untuk adendum, tanpa tambahan kata PKWT.',
                'I1' => 'Durasi dalam angka bulan, 1 sampai 120, misalnya 6. Jangan menulis kata bulan.',
                'J1' => 'Tanggal akhir kontrak. Gunakan tanggal Excel atau YYYY-MM-DD, misalnya 2026-06-30.',
            ];
            foreach ($notes as $cell => $note) {
                $sheet->getComment($cell)->getText()->createTextRun($note);
            }
            $validation = $sheet->getCell('H2')->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST)
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setAllowBlank(false)->setShowDropDown(true)->setShowErrorMessage(true)
                ->setErrorTitle('Jenis history tidak valid')->setError('Pilih PKWT atau ADENDUM.')
                ->setFormula1('"PKWT,ADENDUM"');
            for ($row = 3; $row <= 1001; $row++) {
                $sheet->getCell('H' . $row)->setDataValidation(clone $validation);
            }
        }];
    }
}
