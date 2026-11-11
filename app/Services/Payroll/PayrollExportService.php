<?php

namespace App\Services\Payroll;

use App\Models\ImportHistory;
use Generator;
use XMLWriter;
use ZipArchive;

class PayrollExportService
{
    public function write(ImportHistory $history, array $summary, string $destination): void
    {
        $files=[]; $opened=false;
        $zip=new ZipArchive();
        try {
            if ($zip->open($destination,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) { throw new \RuntimeException('Cannot create payroll archive.'); }
            $opened=true;
            $sheets=['FORMAT BULK','RINGKASAN','RINCIAN HITUNG','PARAMETER'];
            if ($history->source === ImportHistory::SOURCE_HRIS) { $sheets[] = 'RINCIAN KOMPONEN'; }
            $relations=''; $overrides=''; $workbook='';
            foreach ($sheets as $i=>$name) {
                $n=$i+1;
                $path=tempnam(dirname($destination),'payroll-sheet-');
                if (!$path) { throw new \RuntimeException('Cannot create temporary worksheet.'); }
                $files[]=$path;
                $this->writeSheet($path,$this->rows($name,$history,$summary),$name);
                if (!$zip->addFile($path,'xl/worksheets/sheet'.$n.'.xml')) { throw new \RuntimeException('Cannot add payroll worksheet.'); }
                $workbook.='<sheet name="'.$name.'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
                $relations.='<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
                $overrides.='<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            }
            $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$overrides.'</Types>');
            $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$workbook.'</sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relations.'<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/styles.xml','<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF173A5E"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf><xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1" applyNumberFormat="1"><alignment wrapText="1" vertical="top"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
            if (!$zip->close()) { throw new \RuntimeException('Cannot finish payroll archive.'); }
            $opened=false;
        } finally {
            if ($opened) { try { $zip->close(); } catch (\Throwable $ignored) { /* Preserve the original write failure. */ } }
            foreach ($files as $path) { if (is_file($path)) { unlink($path); } }
        }
    }

    private function rows(string $name, ImportHistory $history, array $summary): Generator
    {
        $fromHris = ($summary['settings']['income_source'] ?? 'excel') === 'hris';
        if ($name === 'RINCIAN KOMPONEN') {
            yield ['NIK', 'Nama', 'Kode', 'Komponen', 'Jenis', 'Tetap', 'Nominal', 'Perlakuan payroll'];
            foreach ($history->items()->where('category', 'updated')->orderBy('id')->cursor() as $item) {
                foreach ($item->payload['custom_components'] ?? [] as $component) {
                    yield [$item->nik, $item->employee_name, $component['code'], $component['name'],
                        $component['type'] === 'income' ? 'Pemasukan' : 'Potongan', $component['fixed'] ? 'Ya' : 'Tidak',
                        $component['amount'], !empty($component['included_in_wage']) ? 'Rincian upah; sudah dibayar melalui Riwayat Upah' : 'Tambahan/potongan kas'];
                }
            }
            return;
        }
        if ($name==='RINGKASAN') {
            yield ['Komponen','Nilai'];
            foreach (['employer_name'=>'Perusahaan','period'=>'Periode payroll','tax_period'=>'Masa pajak','start_date'=>'Mulai cut-off','end_date'=>'Akhir cut-off','pay_date'=>'Tanggal gajian','rules_version'=>'Versi aturan','skema_pajak'=>'Skema pajak'] as $key=>$label) { yield [$label,$summary['settings'][$key]]; }
            yield ['Dihitung pada',now()->toIso8601String()];
            if (isset($summary['separate_count'])) { yield ['Karyawan diproses terpisah', $summary['separate_count']]; }
            if ($fromHris) {
                yield ['Sumber penghasilan','Riwayat gaji, presensi, roster, dan realisasi lembur yang disahkan'];
                yield ['Masa kerja','Dipisahkan dari tunjangan tetap ke kolom N'];
                foreach (['basic_salary'=>'Gaji pokok','other_fixed_allowance'=>'Tunjangan tetap lain','meal'=>'Uang makan','tenure'=>'Tunjangan masa kerja','roster_incentive'=>'Insentif roster','bonus'=>'Bonus'] as $key=>$label) { yield [$label,$summary['totals'][$key]]; }
            }
            foreach (['cash_income'=>'Penghasilan kas setelah unpaid/alpa','overtime'=>'Lembur','tax_allowance'=>'Tunjangan pajak','pph21'=>'PPh 21','employee_bpjs'=>'BPJS karyawan','employer_bpjs'=>'BPJS perusahaan','take_home'=>'Total diterima','employer_cost'=>'Kas payroll + BPJS perusahaan'] as $key=>$label) { yield [$label,$summary['totals'][$key]]; }
            yield ['Catatan','Gross-up: AM menambah pendapatan dan AD mengurangi kas. PPh negatif adalah pengembalian ke pegawai. employer_cost = kas payroll + BPJS perusahaan, tidak termasuk biaya natura. Status final dan penyetuju tersimpan di HRIS.'];
            yield ['Pembulatan','PPh dibulatkan ke bawah ke rupiah penuh; PKP ke ribuan ke bawah. Iuran per komponen ke rupiah terdekat; rekonsiliasi tagihan BPJS tetap dilakukan HR.'];
            foreach (config('payroll.sources') as $title=>$url) { yield [$title,$url]; }
            return;
        }
        if ($name==='FORMAT BULK') { yield $history->source === ImportHistory::SOURCE_HRIS
            ? array_merge(PayrollWorkbook::HEADINGS, ['PEMASUKAN TAMBAHAN', 'POTONGAN TAMBAHAN']) : PayrollWorkbook::HEADINGS; }
        elseif ($name==='PARAMETER') { yield PayrollWorkbook::PARAMETERS; }
        else {
            $headings = ['NIK','Nama','PTKP','Masa pajak','Metode','Kategori TER','Tarif TER (%)','Penghasilan kas setelah unpaid/alpa','Natura kena pajak','Tunjangan pajak','Bruto pajak','PPh 21 (negatif = kembali)','Dasar BPJS Kes','Dasar BPJS TK','Dasar JP','Batas JP','Kes pegawai','Keluarga tambahan','JHT pegawai','JP pegawai','Kes perusahaan','JHT perusahaan','JP perusahaan','JKK perusahaan','JKM perusahaan','Total BPJS pegawai','Total BPJS perusahaan','Total diterima','Biaya perusahaan','Bruto setahun','Biaya jabatan','PKP','PPh setahun','PPh dipotong sebelumnya','Versi aturan'];
            yield $fromHris ? array_merge($headings, ['ID riwayat gaji', 'Gaji pokok', 'Tunjangan tetap lain', 'Tahun masa kerja', 'Hari makan', 'Hari insentif', 'Menit lembur disahkan']) : $headings;
        }
        foreach ($history->items()->where('category','updated')->orderBy('id')->cursor() as $item) {
            $p=$item->payload; $r=$p['result']; $b=$r['bpjs']; $d=$r['tax_detail'];
            if ($name==='FORMAT BULK') {
                $values=[];
                for ($i=1;$i<=39;$i++) { $values[]=$p['columns'][\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i)] ?? ''; }
                if ($history->source === ImportHistory::SOURCE_HRIS) {
                    $custom = collect($p['custom_components'] ?? []);
                    array_push($values, $custom->where('type', 'income')->where('included_in_wage', '!=', true)->sum('amount'), $custom->where('type', 'deduction')->sum('amount'));
                }
                yield $values;
            } elseif ($name==='PARAMETER') { yield array_map(function ($key) use ($p) { return $p['parameters'][$key] ?? ''; },PayrollWorkbook::PARAMETERS); }
            else {
                $values = [$item->nik,$p['employee_name'],$p['input']['ptkp'],$r['tax_period'],$d['method'],$r['category'],isset($d['rate_bps'])?$d['rate_bps']/100:null,$p['input']['cash_income'],$p['input']['natura'],$r['tax_allowance'],$r['tax_gross'],$r['pph21'],$b['health_basis'],$b['tk_basis'],$b['jp_basis'],$b['jp_cap'],$b['employee_health'],$b['extra_family'],$b['employee_jht'],$b['employee_jp'],$b['employer_health'],$b['employer_jht'],$b['employer_jp'],$b['employer_jkk'],$b['employer_jkm'],$r['employee_bpjs'],$r['employer_bpjs'],$r['take_home'],$r['employer_cost'],$d['annual_gross']??null,$d['job_expense']??null,$d['pkp']??null,$r['last_period']?$d['tax']:null,$d['previous_tax']??null,$r['rules_version']];
                if ($fromHris) {
                    $h = $p['hris'];
                    array_push($values, $h['salary_history_id'], $h['basic_salary'], $h['other_fixed_allowance'], $h['tenure_years'], count($h['meal_attendance']), count($h['roster_incentive']), array_sum(array_column($h['overtime'], 'minutes')));
                }
                yield $values;
            }
        }
    }

    private function writeSheet(string $path, Generator $rows, string $name): void
    {
        // Streaming XML keeps a 5,000+ employee export out of PhpSpreadsheet's in-memory cell collection.
        $xml=new XMLWriter();
        if (!$xml->openUri($path)) { throw new \RuntimeException('Cannot open worksheet output.'); }
        $xml->startDocument('1.0','UTF-8');
        $xml->startElementNS(null,'worksheet','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xml->startElement('sheetViews'); $xml->startElement('sheetView'); $xml->writeAttribute('workbookViewId','0');
        $xml->startElement('pane'); foreach (['xSplit'=>'2','ySplit'=>'1','topLeftCell'=>'C2','activePane'=>'bottomRight','state'=>'frozen'] as $key=>$value) { $xml->writeAttribute($key,$value); }
        $xml->endElement(); $xml->endElement(); $xml->endElement();
        $xml->startElement('cols');
        foreach ($name==='RINGKASAN' ? [[1,1,40],[2,2,110]] : [[1,$name==='RINCIAN HITUNG' ? 42 : 39,25]] as [$min,$max,$width]) {
            $xml->startElement('col'); foreach (['min'=>$min,'max'=>$max,'width'=>$width,'customWidth'=>1] as $key=>$value) { $xml->writeAttribute($key,(string) $value); }
            $xml->endElement();
        }
        $xml->endElement();
        $xml->startElement('sheetData'); $number=0; $columns=0;
        foreach ($rows as $row) {
            $number++; $columns=max($columns,count($row));
            $xml->startElement('row'); $xml->writeAttribute('r',(string) $number);
            if ($number===1) { $xml->writeAttribute('ht','65'); $xml->writeAttribute('customHeight','1'); }
            elseif ($name==='RINGKASAN' && strlen((string) ($row[1] ?? ''))>80) { $xml->writeAttribute('ht','60'); $xml->writeAttribute('customHeight','1'); }
            foreach ($row as $i=>$value) {
                if ($value===null) { continue; }
                $numeric=is_int($value)||is_float($value);
                $xml->startElement('c'); $xml->writeAttribute('r',\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i+1).$number);
                $xml->writeAttribute('s',$number===1?'1':($numeric?(floor($value)==$value?'2':'3'):'4'));
                if ($numeric) { $xml->writeElement('v',(string) $value); }
                else {
                    $xml->writeAttribute('t','inlineStr'); $xml->startElement('is');
                    $xml->startElement('t'); $xml->writeAttribute('xml:space','preserve');
                    $xml->text(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',(string) $value));
                    $xml->endElement(); $xml->endElement();
                }
                $xml->endElement();
            }
            $xml->endElement();
            if ($number%200===0) { $xml->flush(); }
        }
        $xml->endElement();
        $xml->startElement('autoFilter'); $xml->writeAttribute('ref','A1:'.\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columns).$number); $xml->endElement();
        $xml->endElement(); $xml->endDocument(); $xml->flush();
    }
}
