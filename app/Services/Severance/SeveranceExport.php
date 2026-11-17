<?php

namespace App\Services\Severance;

use App\Jobs\ProcessSeveranceFile;
use App\Models\ImportHistory;
use App\Models\SeveranceRequest;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class SeveranceExport
{
    private const FORMAT_VERSION = 'sections-a-e-v1';

    public function selection(ImportHistory $batch, User $actor, array $ids, bool $all = false)
    {
        app(SeveranceService::class)->authorizeBatch($actor, $batch);
        abort_unless(app(SeveranceService::class)->canManage($actor, $batch->created_by), 403);
        $query = SeveranceRequest::where('import_history_id', $batch->id)->where('status', 'approved');
        if (!$all) {
            if (!$ids) {
                $this->invalid('Pilih pengajuan final yang akan dicetak/diexport.');
            }
            $query->whereIn('id', $ids);
            abort_unless($query->count() === count(array_unique($ids)), 409, 'Pilihan memuat pengajuan yang belum final atau berasal dari batch lain.');
        }
        $count = $query->count();
        if (!$count || $count > (int) config('severance.max_export_rows')) {
            $this->invalid('Pilih 1–' . config('severance.max_export_rows') . ' pengajuan final per export.');
        }
        return $query->orderBy('id');
    }

    public function enqueue(ImportHistory $batch, User $actor, array $ids, bool $all): ImportHistory
    {
        app(SeveranceService::class)->requireQueue();
        $ids = $this->selection($batch, $actor, $ids, $all)->pluck('id')->all();
        $key = hash('sha256', 'export|' . self::FORMAT_VERSION . '|' . $actor->id . '|' . implode(',', $ids));
        $history = ImportHistory::firstOrCreate(['severance_process_key' => $key], [
            'import_id' => (string) Str::uuid(),
            'import_type' => ImportHistory::TYPE_SEVERANCE_EXPORT,
            'module' => 'severance_export',
            'source' => 'hris',
            'status' => 'queued',
            'created_by' => (string) $actor->id,
            'disk' => 'local',
            'total_rows' => count($ids),
            'summary' => ['batch_id' => $batch->id, 'ids' => $ids],
        ]);
        if ($history->wasRecentlyCreated) {
            try {
                ProcessSeveranceFile::dispatch($history->id);
            } catch (Throwable $exception) {
                $history->update(['status' => 'failed', 'error_message' => 'Export belum masuk antrean. Gunakan Coba lagi setelah worker tersedia.']);
                throw $exception;
            }
        }
        return $history;
    }

    public function write(ImportHistory $history): void
    {
        $actor = User::findOrFail($history->created_by);
        $batch = ImportHistory::findOrFail($history->summary['batch_id']);
        $entries = $this->selection($batch, $actor, $history->summary['ids']);
        $book = $this->template();
        $sheet = $book->getActiveSheet();
        $sheet->setCellValue('A1', 'PENGAJUAN DAN HASIL PERHITUNGAN PESANGON');
        $sheet->setCellValue('A2', 'Nilai bruto sebelum pajak. Hanya pengajuan yang telah disetujui Supervisor Payroll dan Supervisor HI.');
        $extra = ['TANGGAL AKHIR KERJA', 'AWAL PERHITUNGAN', 'A. PESANGON / KOMPENSASI / UANG PISAH', 'B. PENGHARGAAN MASA KERJA', 'C. PENGGANTIAN HAK CUTI', 'TOTAL BRUTO', 'PERIODE V PAYSLIP FULL', 'Supervisor PAYROLL', 'WAKTU APPROVAL PAYROLL', 'Supervisor HI', 'WAKTU APPROVAL HI'];
        $this->row($sheet, 7, array_merge(SeveranceWorkbook::HEADINGS, $extra));
        $sheet->setCellValue('M7', 'D. ONGKOS PULANG');
        $details = $book->createSheet()->setTitle('RINCIAN HITUNG');
        $this->row($details, 1, ['NIK', 'NAMA', 'PASAL', 'KOMPONEN', 'RUMUS', 'NOMINAL', 'VERSI ATURAN']);
        $row = 8;
        $detailRow = 2;
        $processed = 0;
        try {
            foreach ($entries->cursor() as $entry) {
                $result = $entry->calculation;
                abort_unless($result && hash_equals($entry->calculation_hash, hash('sha256', json_encode($result))), 409, 'Arsip perhitungan tidak konsisten. Hubungi admin.');
                $salary = $entry->salary_snapshot;
                $metadata = $entry->source_metadata ?? [];
                $lines = array_column($result['lines'], 'amount', 'code');
                $approvers = [];
                foreach ($entry->decisions ?? [] as $decision) {
                    $approvers[$decision['action']] = $decision['actor'];
                }
                $values = [
                    $row - 7,
                    (string) $entry->employee_nik,
                    $entry->employee_name,
                    Date::PHPToExcel($entry->entry_date),
                    $result['years'] . ' Tahun ' . $result['months'] . ' Bulan ' . $result['days'] . ' Hari',
                    $metadata['F'] ?? '',
                    $metadata['G'] ?? '',
                    $metadata['H'] ?? '',
                    $metadata['I'] ?? '',
                    $metadata['J'] ?? '',
                    $metadata['K'] ?? '',
                    $entry->reason,
                    (float) $entry->return_fare,
                    $salary['basic_salary'],
                    $salary['tenure_allowance'],
                    $salary['position_allowance'],
                    (float) $entry->leave_days,
                    (string) $entry->article,
                    $metadata['S'] ?? '',
                    $metadata['T'] ?? '',
                    Date::PHPToExcel($entry->termination_date),
                    Date::PHPToExcel($entry->calculation_start),
                    ($lines['severance'] ?? 0) + ($lines['separation'] ?? 0) + ($lines['compensation'] ?? 0),
                    $lines['service_award'] ?? 0,
                    $lines['leave'] ?? 0,
                    $result['total'],
                    $salary['period'],
                    $approvers['payroll_approved'] ?? $entry->payroll_approved_by,
                    $entry->payroll_approved_at->timezone('Asia/Makassar')->format('Y-m-d H:i:s'),
                    $approvers['hi_approved'] ?? $entry->hi_approved_by,
                    $entry->hi_approved_at->timezone('Asia/Makassar')->format('Y-m-d H:i:s')
                ];
                $this->row($sheet, $row++, $values);
                $display = app(SeveranceResultFormatter::class)->format($result);
                foreach ($display['lines'] as $line) {
                    $this->row($details, $detailRow++, [
                        (string) $entry->employee_nik,
                        $entry->employee_name,
                        (string) $entry->article,
                        $line['label'],
                        $line['formula'],
                        $line['amount'],
                        $result['rules_version']
                    ]);
                }
                $this->row($details, $detailRow, [(string) $entry->employee_nik, $entry->employee_name,
                    (string) $entry->article, $display['total_label'], 'Jumlah komponen di atas (bruto sebelum pajak)',
                    $result['total'], $result['rules_version']]);
                $details->getStyle('A' . $detailRow . ':G' . $detailRow)->getFont()->setBold(true);
                $detailRow++;
                if (++$processed % 100 === 0) {
                    $history->update(['success_count' => $processed]);
                }
            }
            $this->style($sheet, 'AE', $row - 1, 7);
            $this->style($details, 'G', $detailRow - 1, 1);
            $details->getColumnDimension('D')->setWidth(36);
            $details->getColumnDimension('E')->setWidth(60);
            $details->getStyle('D2:E' . ($detailRow - 1))->getAlignment()->setWrapText(true);
            foreach (['D', 'U', 'V'] as $col) {
                $sheet->getStyle($col . '8:' . $col . ($row - 1))->getNumberFormat()->setFormatCode('dd mmm yyyy');
            }
            foreach (['M', 'N', 'O', 'P', 'W', 'X', 'Y', 'Z'] as $col) {
                $sheet->getStyle($col . '8:' . $col . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00');
            }
            $details->getStyle('F2:F' . ($detailRow - 1))->getNumberFormat()->setFormatCode('#,##0.00');
            $directory = 'severance/' . $history->import_id;
            Storage::disk('local')->makeDirectory('private/' . $directory);
            $absolute = Storage::disk('local')->path('private/' . $directory);
            $writer = new Xlsx($book);
            $writer->setPreCalculateFormulas(false);
            $writer->save($absolute . '/hasil.xlsx');
            $history->update([
                'status' => 'completed',
                'success_count' => $processed,
                'finished_at' => now(),
                'file_path' => $directory . '/hasil.xlsx',
                'file_name' => 'hasil-pesangon-' . $batch->id . '.xlsx'
            ]);
            app(SeveranceService::class)->audit($actor, $history, 'exported');
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function template(): Spreadsheet
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('PENGAJUAN');
        $sheet->mergeCells('A1:T1')->setCellValue('A1', 'PENGAJUAN PESANGON DAN KOMPENSASI');
        $sheet->mergeCells('A2:T2')->setCellValue('A2', 'Isi NIK, alasan, ongkos pulang (M), dan pasal mulai baris 8. Biodata utama diambil dari HRIS.');
        $sheet->mergeCells('A3:T3')->setCellValue('A3', 'Gaji dan tunjangan diisi otomatis setelah approval Supervisor Payroll. Sisa cuti wajib diisi Supervisor HI.');
        $this->row($sheet, 7, SeveranceWorkbook::HEADINGS);
        $this->style($sheet, 'T', 8, 7);
        $sheet->getStyle('B:B')->getNumberFormat()->setFormatCode('@');
        return $book;
    }

    private function row(Worksheet $sheet, int $number, array $values): void
    {
        foreach ($values as $i => $value) {
            $sheet->setCellValueExplicit(
                Coordinate::stringFromColumnIndex($i + 1) . $number,
                $value,
                is_int($value) || is_float($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING
            );
        }
    }

    private function style(Worksheet $sheet, string $lastColumn, int $lastRow, int $header): void
    {
        $sheet->getStyle('A' . $header . ':' . $lastColumn . $header)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '173A5E']],
            'alignment' => ['wrapText' => true, 'vertical' => 'center'],
        ]);
        $sheet->getRowDimension($header)->setRowHeight(48);
        $sheet->getDefaultColumnDimension()->setWidth(21);
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->freezePane('D' . ($header + 1));
        $sheet->setAutoFilter('A' . $header . ':' . $lastColumn . $lastRow);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
    }

    private function invalid(string $message): void
    {
        throw ValidationException::withMessages(['export' => $message]);
    }
}
