<?php

namespace App\Services\ImportHistory;

use App\Models\ImportHistory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class ImportHistoryService
{
    private const MAX_SAMPLE_ITEMS = 50;

    public function createQueued(array $attributes): ?ImportHistory
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $allowed = [
            'import_id',
            'import_type',
            'module',
            'source',
            'file_name',
            'file_path',
            'disk',
            'mime_type',
            'file_size',
            'created_by',
            'summary',
        ];

        $data = Arr::only($attributes, $allowed);
        $data['import_id'] = $data['import_id'] ?? (string) Str::uuid();
        $data['source'] = $data['source'] ?? ImportHistory::SOURCE_EXCEL;
        $data['status'] = ImportHistory::STATUS_QUEUED;
        $data['summary'] = $this->normalizeArray($data['summary'] ?? []);

        return ImportHistory::create($data);
    }

    public function markProcessing(?int $historyId, array $summary = []): void
    {
        if (!$this->canWrite($historyId)) {
            return;
        }

        DB::transaction(function () use ($historyId, $summary) {
            $history = ImportHistory::whereKey($historyId)->lockForUpdate()->first();

            if (!$history || $history->status === ImportHistory::STATUS_FAILED) {
                return;
            }

            $history->status = ImportHistory::STATUS_PROCESSING;
            $history->started_at = $history->started_at ?: now();
            $history->summary = $this->mergeArray($history->summary, $summary);
            $history->save();
        });
    }

    public function addChunkResult(
        ?int $historyId,
        int $totalRows,
        int $successCount,
        int $skippedCount = 0,
        int $failedCount = 0,
        array $failureSamples = [],
        array $summary = [],
        int $insertedCount = 0,
        int $updatedCount = 0
    ): void {
        if (!$this->canWrite($historyId)) {
            return;
        }

        DB::transaction(function () use (
            $historyId,
            $totalRows,
            $successCount,
            $skippedCount,
            $failedCount,
            $failureSamples,
            $summary,
            $insertedCount,
            $updatedCount
        ) {
            $history = ImportHistory::whereKey($historyId)->lockForUpdate()->first();

            if (!$history || $history->status === ImportHistory::STATUS_FAILED) {
                return;
            }

            $history->status = ImportHistory::STATUS_PROCESSING;
            $history->started_at = $history->started_at ?: now();
            $history->total_rows = (int) $history->total_rows + max(0, $totalRows);
            $history->success_count = (int) $history->success_count + max(0, $successCount);
            $history->skipped_count = (int) $history->skipped_count + max(0, $skippedCount);
            $history->failed_count = (int) $history->failed_count + max(0, $failedCount);
            $history->inserted_count = (int) $history->inserted_count + max(0, $insertedCount);
            $history->updated_count = (int) $history->updated_count + max(0, $updatedCount);
            $history->summary = $this->mergeArray($history->summary, $summary);
            $history->failure_samples = $this->appendSamples($history->failure_samples, $failureSamples);
            $history->save();
        });
    }

    public function markCompleted(?int $historyId, array $summary = []): void
    {
        if (!$this->canWrite($historyId)) {
            return;
        }

        DB::transaction(function () use ($historyId, $summary) {
            $history = ImportHistory::whereKey($historyId)->lockForUpdate()->first();

            if (!$history || $history->status === ImportHistory::STATUS_FAILED) {
                return;
            }

            $hasErrors = ((int) $history->failed_count > 0) || ((int) $history->skipped_count > 0);

            $history->status = $hasErrors
                ? ImportHistory::STATUS_COMPLETED_WITH_ERRORS
                : ImportHistory::STATUS_COMPLETED;
            $history->started_at = $history->started_at ?: now();
            $history->finished_at = now();
            $history->summary = $this->mergeArray($history->summary, $summary);
            $history->save();
        });
    }

    public function markFailed(?int $historyId, $error, array $summary = [], array $failureSamples = []): void
    {
        if (!$this->canWrite($historyId)) {
            return;
        }

        DB::transaction(function () use ($historyId, $error, $summary, $failureSamples) {
            $history = ImportHistory::whereKey($historyId)->lockForUpdate()->first();

            if (!$history) {
                return;
            }

            $history->status = ImportHistory::STATUS_FAILED;
            $history->started_at = $history->started_at ?: now();
            $history->finished_at = now();
            $history->failed_count = max(1, (int) $history->failed_count);
            $history->summary = $this->mergeArray($history->summary, $summary);
            $history->failure_samples = $this->appendSamples($history->failure_samples, $failureSamples);
            $history->error_message = $this->formatErrorMessage($error);
            $history->save();
        });
    }

    public function syncMediaSummary(?int $historyId, array $summary, ?string $errorMessage = null): void
    {
        if (!$this->canWrite($historyId)) {
            return;
        }

        DB::transaction(function () use ($historyId, $summary, $errorMessage) {
            $history = ImportHistory::whereKey($historyId)->lockForUpdate()->first();

            if (!$history) {
                return;
            }

            $status = $summary['status'] ?? ImportHistory::STATUS_PROCESSING;
            $successCount = (int) ($summary['success_count'] ?? 0);
            $skippedCount = (int) ($summary['skipped_count'] ?? 0);

            $history->started_at = $history->started_at ?: now();
            $history->total_rows = (int) ($summary['total_entries'] ?? $history->total_rows);
            $history->success_count = $successCount;
            $history->skipped_count = $skippedCount;
            $history->summary = $this->mergeArray($history->summary, $this->publicMediaSummary($summary));
            $history->failure_samples = $this->appendSamples(
                [],
                $this->mediaFailureSamples($summary['items'] ?? [])
            );

            if ($status === ImportHistory::STATUS_FAILED || $status === 'failed') {
                $history->status = ImportHistory::STATUS_FAILED;
                $history->failed_count = max(1, (int) $history->failed_count);
                $history->finished_at = now();
                $history->error_message = $this->formatErrorMessage($errorMessage ?: ($summary['error_message'] ?? 'Import ZIP gagal diproses.'));
            } elseif ($status === ImportHistory::STATUS_COMPLETED || $status === 'completed') {
                $history->status = $skippedCount > 0
                    ? ImportHistory::STATUS_COMPLETED_WITH_ERRORS
                    : ImportHistory::STATUS_COMPLETED;
                $history->finished_at = now();
            } elseif ($status === ImportHistory::STATUS_QUEUED || $status === 'queued') {
                $history->status = ImportHistory::STATUS_QUEUED;
            } else {
                $history->status = ImportHistory::STATUS_PROCESSING;
            }

            $history->save();
        });
    }

    public function isEnabled(): bool
    {
        try {
            return Schema::hasTable('import_histories');
        } catch (Throwable $exception) {
            return false;
        }
    }

    private function canWrite(?int $historyId): bool
    {
        return $historyId !== null && $this->isEnabled();
    }

    private function mergeArray($current, array $incoming): array
    {
        return array_merge($this->normalizeArray($current), $this->normalizeArray($incoming));
    }

    private function appendSamples($current, array $samples): array
    {
        $items = $this->normalizeArray($current);

        foreach ($samples as $sample) {
            if (count($items) >= self::MAX_SAMPLE_ITEMS) {
                break;
            }

            if (!is_array($sample)) {
                continue;
            }

            $items[] = [
                'status' => (string) ($sample['status'] ?? 'skip'),
                'row' => $sample['row'] ?? null,
                'file' => isset($sample['file']) ? (string) $sample['file'] : null,
                'nik' => isset($sample['nik']) ? (string) $sample['nik'] : null,
                'message' => Str::limit((string) ($sample['message'] ?? '-'), 240, ''),
            ];
        }

        return array_values($items);
    }

    private function normalizeArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function formatErrorMessage($error): string
    {
        if ($error instanceof Throwable) {
            $error = $error->getMessage();
        }

        return Str::limit((string) $error, 500, '');
    }

    private function publicMediaSummary(array $summary): array
    {
        unset($summary['processed_niks']);

        if (isset($summary['items']) && is_array($summary['items'])) {
            $summary['items'] = array_slice($summary['items'], 0, 20);
        }

        return $summary;
    }

    private function mediaFailureSamples(array $items): array
    {
        return collect($items)
            ->filter(function ($item) {
                return is_array($item) && ($item['status'] ?? null) !== 'success';
            })
            ->map(function ($item) {
                return [
                    'status' => (string) ($item['status'] ?? 'skip'),
                    'file' => $item['file'] ?? null,
                    'message' => $item['message'] ?? '-',
                ];
            })
            ->values()
            ->all();
    }
}
