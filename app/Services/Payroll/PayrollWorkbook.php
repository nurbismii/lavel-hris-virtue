<?php

namespace App\Services\Payroll;

use Generator;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Illuminate\Validation\ValidationException;

class PayrollWorkbook
{
    public const HEADINGS = [
        'NIK 工号','No KTP : 身份证号','DEPARTMENT 大部门','DIVISI 部门',"POSITION \n岗位",'EMPLOYEE SALARY STATUS 员工工资状况','WARNING LETTER END DATE 警告结束日期','TOTAL WORK DAYS 出勤','HOUR MACHINE (HOUR) 操作 （小时）','SALARY RECEIVED 工资','MEAL ALLOWANCE 餐补','TUNJANGAN PENGAWAS  岗位补贴','TUNJANGAN TRANSPORT & PULSA','TUNJANGAN MASA KERJA 职业补贴','KOEFISIEN TUNJANGAN JABATAN C 岗位系数C类','OVER TIME 加班费','HOUR MACHINE 操作费','BPJS KETENAGAKERJAAN (EMPLOYEE)','BPJS-TK (JP)劳动保险（退休金）','BPJS KESEHATAN (EMPLOYEE)',"TUNJANGAN FUNGSIONAL\n职能津贴", "TUNJANGAN LAPANGAN\n现场津贴",'BONUS','INSENTIF 奖励','RAPEL 欠薪','PESANGON/KOMPENSASI/UANG PISAH',"DEDUCTION UNPAID LEAVE\n无薪假扣除", "DEDUCTION ALPA\n旷工扣除", "DEDUCTION LAIN-LAIN\n其他扣除", "DEDUCTION PPH 21\n所得税扣除",'TOTAL SALARY 实发工资','BANK ACCOUNT 银行账号','BANK ACCOUNT NAME 银行名称','PERIODE','THR','MULAI_PERIODE','AKHIR_PERIODE','TANGGAL_GAJIAN', 'TUNJANGAN PAJAK',
    ];
    public const PARAMETERS = ['nik','ptkp','upah_bpjs_tk','upah_bpjs_kes','upah_minimum','jkk_persen','jht_aktif','jp_aktif','kes_aktif','jkk_jkm_aktif','keluarga_tambahan','masa_terakhir','bulan_mulai_pajak','ytd_bulan','ytd_bruto','ytd_iuran','ytd_zakat','ytd_pph21','natura','iuran_pensiun_lain','zakat','subjek_pajak','fasilitas','nik_npwp_valid'];
    public const INCOME_COLUMNS = ['J','K','L','M','N','O','P','Q','U','V','W','X','Y','Z','AI'];
    private const OVERTIME_HEADINGS = ['ID_PERINTAH_LEMBUR', 'MENIT_DISAHKAN'];

    public function verifiedOvertime(string $path): array
    {
        $info = array_column((new Xlsx())->listWorksheetInfo($path), null, 'worksheetName')['LEMBUR'] ?? null;
        if (!$info || $info['totalColumns'] !== 2 || $info['totalRows'] > config('payroll.max_rows') + 1) {
            $this->invalid('Sumber HRIS memerlukan sheet LEMBUR sesuai template, maksimal 10.000 perintah.');
        }

        $minutes = [];
        foreach ($this->rows($path, 'LEMBUR', $info['totalRows'], self::OVERTIME_HEADINGS) as $rows) {
            foreach ($rows as $line => [$id, $value]) {
                if (!preg_match('/^[1-9][0-9]{0,14}$/D', (string) $id)
                    || !preg_match('/^(0|[1-9][0-9]{0,3})$/D', (string) $value) || isset($minutes[$id])) {
                    $this->invalid('LEMBUR baris '.$line.' harus memuat ID perintah unik dan menit yang disahkan (angka bulat, termasuk 0).');
                }
                $minutes[(int) $id] = (int) $value;
            }
        }

        return $minutes;
    }

    public function read(string $path): Generator
    {
        $reader = new Xlsx();
        $reader->setReadDataOnly(true);
        $info = array_column($reader->listWorksheetInfo($path), null, 'worksheetName');
        foreach (['FORMAT BULK'=>39,'PARAMETER'=>count(self::PARAMETERS)] as $name=>$columns) {
            if (!isset($info[$name]) || $info[$name]['totalRows'] > config('payroll.max_rows') + 1 || $info[$name]['totalColumns'] > $columns) {
                $this->invalid('Gunakan sheet FORMAT BULK dan PARAMETER sesuai template; maksimal 10.000 karyawan.');
            }
        }
        $parameters = [];
        foreach ($this->rows($path, 'PARAMETER', $info['PARAMETER']['totalRows'], self::PARAMETERS) as $rows) {
            foreach ($rows as $row) {
                $nik = trim((string) $row[0]);
                if ($nik === '' || isset($parameters['nik:'.$nik])) { $this->invalid('NIK pada PARAMETER kosong atau duplikat.'); }
                $parameters['nik:'.$nik] = array_combine(self::PARAMETERS, $row);
            }
        }
        $headings = array_slice(self::HEADINGS, 0, $info['FORMAT BULK']['totalColumns']);
        if (count($headings) < 38) { $this->invalid('Header FORMAT BULK harus memuat 38 kolom contoh atau 39 kolom hasil payroll.'); }
        $seen = [];
        foreach ($this->rows($path, 'FORMAT BULK', $info['FORMAT BULK']['totalRows'], $headings) as $rows) {
            $chunk = [];
            foreach ($rows as $number=>$values) {
                $nik = trim((string) $values[0]);
                $columns = [];
                foreach ($values as $index=>$value) { $columns[Coordinate::stringFromColumnIndex($index+1)] = $value; }
                $duplicate = isset($seen['nik:'.$nik]);
                $seen['nik:'.$nik] = true;
                $chunk[$number] = ['columns'=>$columns, 'parameters'=>$parameters['nik:'.$nik] ?? [], 'duplicate'=>$duplicate];
            }
            yield $chunk;
        }
    }

    private function rows(string $path, string $name, int $total, array $headings): Generator
    {
        $reader = new Xlsx();
        $reader->setReadDataOnly(true)->setLoadSheetsOnly($name);
        for ($start=2; $start<=max(2,$total); $start+=200) {
            $reader->setReadFilter(new class($start, count($headings)) implements IReadFilter {
                private $start; private $columns;
                public function __construct(int $start, int $columns) { $this->start=$start; $this->columns=$columns; }
                public function readCell(string $columnAddress, int $row, string $worksheetName=''): bool
                {
                    return Coordinate::columnIndexFromString($columnAddress)<=$this->columns && ($row===1 || ($row>=$this->start && $row<$this->start+200));
                }
            });
            $book=$reader->load($path);
            try {
                $sheet=$book->getSheet(0);
                foreach ($headings as $i=>$heading) {
                    if ($this->normalize($sheet->getCell([$i+1,1])->getValue()) !== $this->normalize($heading)) {
                        $this->invalid('Header sheet '.$name.' tidak sesuai template.');
                    }
                }
                $rows=[];
                for ($r=$start; $r<=min($start+199,$total); $r++) {
                    $values=[];
                    for ($c=1; $c<=count($headings); $c++) {
                        $cell=$sheet->getCell([$c,$r]);
                        if ($cell->getDataType()===DataType::TYPE_FORMULA) { $this->invalid('Sheet '.$name.' baris '.$r.' masih berisi formula. Gunakan Paste Values.'); }
                        $value=$cell->getValue();
                        $values[]=$value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText ? $value->getPlainText() : $value;
                    }
                    if (array_filter($values, function ($v) { return $v!==null && $v!==''; })) { $rows[$r]=$values; }
                }
            } finally { $book->disconnectWorksheets(); }
            yield $rows;
        }
    }

    public function template(): Spreadsheet
    {
        $book=new Spreadsheet();
        $sheet=$book->getActiveSheet();
        $sheet->setTitle('FORMAT BULK');
        $sheet->fromArray(self::HEADINGS, null, 'A1');
        $parameter=$book->createSheet()->setTitle('PARAMETER');
        $parameter->fromArray(self::PARAMETERS, null, 'A1');
        $overtime=$book->createSheet()->setTitle('LEMBUR');
        $overtime->fromArray(self::OVERTIME_HEADINGS, null, 'A1');
        foreach ([$sheet,$parameter,$overtime] as $tab) { $this->style($tab); }
        foreach (['A','B','AF','AG','AH','AJ','AK','AL'] as $column) { $sheet->getStyle($column.':'.$column)->getNumberFormat()->setFormatCode('@'); }
        $parameter->getStyle('A:A')->getNumberFormat()->setFormatCode('@');
        $guide=$book->createSheet()->setTitle('PANDUAN');
        $guide->fromArray([
            ['Pengisian payroll gross-up','Keterangan'],
            ['FORMAT BULK','Salin 38 kolom contoh. R/S/T/AD/AE/AM dihitung ulang. Nominal penghasilan dan lembur harus sudah diverifikasi HR.'],
            ['Sumber upah HRIS','Untuk bulan kerja penuh, kosongkan H, J, K, N, P, dan X. J diisi gaji pokok + tunjangan tetap lain. Isi hanya komponen tambahan periode ini agar tunjangan tetap tidak terhitung dua kali. Isi W dengan bonus yang disahkan HR.'],
            ['H/K/N/X','Uang makan Rp17.500 per tanggal hadir dengan jam masuk/pulang. Masa kerja Rp50.000 per tahun penuh per akhir bulan payroll dipisahkan dari tunjangan tetap ke kolom N. Insentif roster dihitung dari hari yang disetujui dalam cut-off × gaji pokok/30.'],
            ['LEMBUR','Khusus sumber HRIS. Isi ID setiap perintah yang diterima dalam cut-off dan menit yang disahkan HR setelah memeriksa realisasi. Isi 0 jika nihil. ID tersedia di Detail Perintah Lembur. Biarkan hanya header bila tidak ada perintah.'],
            ['PARAMETER','Satu baris per NIK. Semua angka rupiah bulat, tanpa pemisah ribuan. Identitas/rekening harus teks.'],
            ['ptkp','TK/0, TK/1, TK/2, TK/3, K/0, K/1, K/2, K/3. Gunakan status pajak awal tahun yang telah diperiksa HR.'],
            ['upah_bpjs_tk / upah_bpjs_kes','Upah bulanan yang dilaporkan: gaji pokok + tunjangan tetap; bukan otomatis total pendapatan atau gaji setelah prorata.'],
            ['upah_minimum','UMK/UMP berlaku pada lokasi kerja untuk dasar minimal BPJS Kesehatan. Jangan isi nominal perkiraan.'],
            ['jkk_persen','Tarif risiko terdaftar: 0.24, 0.54, 0.89, 1.27, 1.74. Fasilitas potongan khusus memerlukan perhitungan terpisah.'],
            ['jht_aktif / jp_aktif / kes_aktif / jkk_jkm_aktif','Isi 1 untuk aktif, 0 bila memang tidak berlaku. Verifikasi kewajiban dan kepesertaan; jangan menonaktifkan untuk mengurangi potongan.'],
            ['keluarga_tambahan','Jumlah keluarga tambahan BPJS Kes di luar keluarga inti yang ditanggung; isi 0 jika tidak ada.'],
            ['masa_terakhir','1 untuk bulan berhenti pada pemberi kerja ini; 0 untuk bulan biasa. Desember otomatis rekonsiliasi Pasal 17.'],
            ['bulan_mulai_pajak / ytd_bulan','Untuk masa terakhir: bulan mulai pada pemberi kerja ini dalam tahun tersebut; jumlah bulan sebelumnya harus lengkap.'],
            ['ytd_bruto / ytd_iuran / ytd_zakat / ytd_pph21','Saldo aktual pada pemberi kerja yang sama sebelum masa ini. Bruto sudah termasuk tunjangan pajak dan premi kena pajak. Iuran hanya iuran pensiun/JHT/JP pegawai yang dapat dikurangkan. Jangan gabungkan penghasilan pemberi kerja lain.'],
            ['natura / iuran_pensiun_lain / zakat','Isi 0 jika nihil. Natura = manfaat nonkas kena pajak tambahan. Iuran pensiun lain dan zakat harus memenuhi syarat PMK 168 serta dibayar melalui pemberi kerja. AC harus sudah mencakup keduanya; parameter ini tidak menambah potongan kas lagi.'],
            ['subjek_pajak','DN_SETAHUN untuk pegawai tetap dengan kewajiban subjektif dalam negeri sepanjang tahun. Datang/meninggalkan Indonesia pada bagian tahun memerlukan perhitungan disetahunkan terpisah.'],
            ['fasilitas','NORMAL. DTP/relaksasi khusus belum diotomatisasi; jangan memilih NORMAL bila fasilitas berlaku.'],
            ['Pesangon / kompensasi','Kolom Z harus nol. Pembayaran dengan perlakuan pajak berbeda harus dipisahkan dan direview.'],
            ['Penghasilan dan potongan','J adalah gaji sebelum potongan AA/AB. AA/AB mengurangi upah yang diperoleh serta bruto pajak. AC (utang/potongan lain) mengurangi kas, bukan bruto pajak. Y hanya rapel upah yang memang dipajaki pada masa ini.'],
            ['Periode','Form menentukan periode payroll, masa pajak terutang, cut-off, dan tanggal gajian. Nilai AH/AJ/AK/AL harus sama atau dikosongkan.'],
            ['Gross-up','AM adalah tunjangan pajak. Total AE = seluruh pendapatan + AM - AA - AB - AC - R - S - T - AD. PPh negatif merupakan pengembalian ke karyawan; tunjangan pajak tidak dibuat negatif.'],
            ['Arsip','Hasil final dikunci sebagai snapshot; ekspor bukan instruksi transfer, bukti potong, atau pelaporan Coretax/SIPP.'],
            ['nik_npwp_valid','Wajib 1 setelah HR memastikan identitas NIK/NPWP valid dan terintegrasi DJP untuk tarif normal. Jika belum valid, tahan payroll dan selesaikan verifikasi pajak lebih dahulu.'],
        ], null, 'A1');
        $guide->getColumnDimension('A')->setWidth(38); $guide->getColumnDimension('B')->setWidth(110);
        $guide->getStyle('A1:B'.$guide->getHighestRow())->getAlignment()->setWrapText(true);
        $guide->getStyle('A1:B1')->getFont()->setBold(true);
        foreach (range(2,$guide->getHighestRow()) as $r) { $guide->getRowDimension($r)->setRowHeight(58); }
        $guide->freezePane('B2');
        $book->setActiveSheetIndex(0);
        return $book;
    }

    public function style($sheet): void
    {
        $last=$sheet->getHighestColumn();
        $sheet->getStyle('A1:'.$last.'1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:'.$last.'1')->getFill()->setFillType('solid')->getStartColor()->setRGB('173A5E');
        $sheet->getStyle('A1:'.$last.'1')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(65);
        for ($c=1; $c<=Coordinate::columnIndexFromString($last); $c++) { $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(23); }
        $sheet->freezePane('C2');
        $sheet->setAutoFilter('A1:'.$last.max(1,$sheet->getHighestRow()));
    }

    private function normalize($value): string { return preg_replace('/\s+/u',' ',trim((string) $value)); }
    private function invalid(string $message): void { throw ValidationException::withMessages(['file'=>$message]); }
}
