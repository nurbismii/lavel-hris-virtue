<?php

namespace App\Jobs;

use App\Services\CvMaker\CvMakerPdfExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessCvMakerPdfBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $batchId;
    public $tries = 2;
    public $timeout = 120;
    public $backoff = 30;

    public function __construct(int $batchId)
    {
        $this->batchId = $batchId;
    }

    public function handle(CvMakerPdfExportService $service): void
    {
        $service->processNext($this->batchId);
    }

    public function failed(?Throwable $exception): void
    {
        app(CvMakerPdfExportService::class)->fail($this->batchId);
    }
}
