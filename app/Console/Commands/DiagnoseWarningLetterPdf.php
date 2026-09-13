<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DiagnoseWarningLetterPdf extends Command
{
    protected $signature = 'warning-letters:diagnose-pdf';

    protected $description = 'Periksa kesiapan environment untuk mengunduh PDF surat peringatan';

    public function handle(): int
    {
        $checks = [
            ['PHP01', 'PHP minimal 8.3', version_compare(PHP_VERSION, '8.3.0', '>=')],
            ['PDF01', 'Package DomPDF tersedia', class_exists(\Dompdf\Dompdf::class)],
            ['QR01', 'Package QR Code tersedia', class_exists(\Endroid\QrCode\QrCode::class)
                && class_exists(\Endroid\QrCode\Writer\SvgWriter::class)],
            ['AST01', 'Logo surat tersedia', is_file(public_path('assets/img/vdni-letter-logo-mark.png'))],
            ['AST02', 'Watermark kanan tersedia', is_file(public_path('assets/img/vdni-letter-watermark-right.png'))],
            ['AST03', 'Watermark kiri tersedia', is_file(public_path('assets/img/vdni-letter-watermark-left.png'))],
            ['FS01', 'Storage framework dapat ditulis', is_writable(storage_path('framework'))],
            ['FS02', 'Bootstrap cache dapat ditulis', is_writable(base_path('bootstrap/cache'))],
        ];

        try {
            $checks[] = ['DB01', 'Tabel pengajuan SP tersedia', Schema::hasTable('warning_letter_requests')];
            $checks[] = ['DB02', 'Kolom token QR tersedia', Schema::hasColumn('warning_letter_requests', 'verification_token')];
            $checks[] = ['DB03', 'Kolom pembatalan tersedia', Schema::hasColumn('warning_letter_requests', 'cancelled_at')];
            $checks[] = ['DB04', 'Kolom soft delete SP tersedia', Schema::hasColumn('sp_report', 'deleted_at')];
        } catch (Throwable $exception) {
            $checks[] = ['DB00', 'Koneksi database dapat digunakan', false];
        }

        $rows = array_map(static function (array $check) {
            return [$check[0], $check[1], $check[2] ? 'OK' : 'GAGAL'];
        }, $checks);
        $this->table(['Kode', 'Pemeriksaan', 'Hasil'], $rows);

        $failed = array_values(array_filter($checks, static fn (array $check) => !$check[2]));
        if ($failed) {
            $this->error('Environment belum siap. Perbaiki pemeriksaan berstatus GAGAL sebelum mencoba download kembali.');
            return self::FAILURE;
        }

        $this->info('Environment PDF surat peringatan siap.');
        return self::SUCCESS;
    }
}
