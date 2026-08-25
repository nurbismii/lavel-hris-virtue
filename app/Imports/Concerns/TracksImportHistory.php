<?php

namespace App\Imports\Concerns;

use App\Services\ImportHistory\ImportHistoryService;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Throwable;

trait TracksImportHistory
{
    protected $importHistoryId;

    public function importHistoryId(): ?int
    {
        return $this->importHistoryId ? (int) $this->importHistoryId : null;
    }

    public static function beforeImport(BeforeImport $event): void
    {
        $import = $event->getConcernable();

        if (method_exists($import, 'importHistoryId')) {
            app(ImportHistoryService::class)->markProcessing($import->importHistoryId());
        }
    }

    public static function afterImport(AfterImport $event): void
    {
        $import = $event->getConcernable();

        if (method_exists($import, 'importHistoryId')) {
            app(ImportHistoryService::class)->markCompleted($import->importHistoryId());
        }
    }

    public function failed(Throwable $exception): void
    {
        app(ImportHistoryService::class)->markFailed($this->importHistoryId(), $exception);
    }

    protected function recordImportChunk(
        int $totalRows,
        int $successCount,
        int $skippedCount = 0,
        int $failedCount = 0,
        array $failureSamples = [],
        array $summary = [],
        int $insertedCount = 0,
        int $updatedCount = 0,
        array $detailItems = []
    ): void {
        app(ImportHistoryService::class)->addChunkResult(
            $this->importHistoryId(),
            $totalRows,
            $successCount,
            $skippedCount,
            $failedCount,
            $failureSamples,
            $summary,
            $insertedCount,
            $updatedCount,
            $detailItems
        );
    }
}
