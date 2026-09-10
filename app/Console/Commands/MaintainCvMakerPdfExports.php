<?php

namespace App\Console\Commands;

use App\Models\CvMakerPdfBatch;
use App\Services\CvMaker\CvMakerPdfExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class MaintainCvMakerPdfExports extends Command
{
    protected $signature = 'cv-maker:maintain-pdf-exports';
    protected $description = 'Pulihkan antrean PDF yang terputus dan hapus hanya file ekspor yang kedaluwarsa.';

    public function handle(CvMakerPdfExportService $service): int
    {
        CvMakerPdfBatch::whereNull('files_purged_at')->where(function ($q) {
            $q->where(function ($expired) {
                $expired->where('expires_at', '<=', now());
            })->orWhere('status', 'cancelled');
        })->orderBy('id')->chunkById(50, function ($batches) use ($service) {
            foreach ($batches as $batch) {
                $lock = Cache::lock('cv-pdf-batch:' . $batch->id, 240);
                if (!$lock->get()) continue;
                try {
                    $batch->refresh();
                    $service->close($batch, 'expired', 'File ekspor telah dibersihkan. Riwayat unduhan tetap tersimpan.');
                    // Server-generated UUID, fixed private prefix, never employee input.
                    if (preg_match('/^[a-f0-9-]{36}$/D', $batch->uuid)
                        && Storage::disk('local')->deleteDirectory('private/' . CvMakerPdfExportService::PREFIX . $batch->uuid)) {
                        $batch->update(['files_purged_at' => now()]);
                    }
                } finally {
                    $lock->release();
                }
            }
        });
        CvMakerPdfBatch::whereIn('status', CvMakerPdfExportService::ACTIVE)
            ->where('updated_at', '<', now()->subMinutes(5))->orderBy('id')->chunkById(50, function ($batches) use ($service) {
                foreach ($batches as $batch) {
                    $batch->touch();
                    $service->dispatch($batch);
                }
            });
        return 0;
    }
}
