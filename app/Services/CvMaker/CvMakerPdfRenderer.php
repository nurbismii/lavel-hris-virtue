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
        $data = app(CvMakerCompareService::class)->pdfVitaeForEmployee($employee);
        $bytes = Pdf::loadView('admin.cv-maker-compare.pdf', [
            'vitae' => $data['vitae'], 'nik' => $employee->nik, 'generatedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4')->setOptions([
            'defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
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
