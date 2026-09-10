<?php

namespace App\Services\CvMaker;

use App\Models\Employee;
use App\Services\Storage\SensitiveFileStorageService;
use Barryvdh\DomPDF\Facade\Pdf;
use RuntimeException;

class CvMakerPdfRenderer
{
    public function render(Employee $employee, string $batchUuid, int $itemId): string
    {
        if (!is_readable(storage_path('fonts/NotoSansSC-Regular.ttf'))) {
            throw new RuntimeException('Font Mandarin PDF tidak tersedia. Pastikan NotoSansSC-Regular.ttf ikut dideploy.');
        }
        $data = app(CvMakerCompareService::class)->pdfVitaeForEmployee($employee);
        $bytes = Pdf::loadView('admin.cv-maker-compare.pdf', [
            'vitae' => $data['vitae'], 'nik' => $employee->nik, 'generatedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4')->setOption([
            'defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isPhpEnabled' => false,
            'isJavascriptEnabled' => false, 'isFontSubsettingEnabled' => true,
            'fontDir' => str_replace('\\', '/', config('dompdf.options.font_dir', storage_path('fonts'))),
            'fontCache' => str_replace('\\', '/', config('dompdf.options.font_cache', storage_path('fonts'))),
        ])->output();
        if (substr($bytes, 0, 5) !== '%PDF-' || strlen($bytes) > (int) config('cv_pdf.max_file_bytes', 10485760)) {
            throw new RuntimeException('Hasil PDF tidak valid atau terlalu besar.');
        }
        $relative = CvMakerPdfExportService::PREFIX . $batchUuid;
        $directory = app(SensitiveFileStorageService::class)->ensurePrivateDirectory($relative);
        $temporary = $directory . '/' . $itemId . '.tmp';
        if (file_put_contents($temporary, $bytes, LOCK_EX) === false
            || !rename($temporary, $directory . '/' . $itemId . '.pdf')) {
            throw new RuntimeException('PDF gagal disimpan.');
        }
        return $relative . '/' . $itemId . '.pdf';
    }
}
