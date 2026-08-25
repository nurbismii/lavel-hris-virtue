<?php

namespace App\Services\ImportHistory;

use App\Models\ImportHistory;
use App\Models\ImportHistoryItem;
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
        int $updatedCount = 0,
        array $detailItems = []
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
            $updatedCount,
            $detailItems
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

            $this->insertDetailItems($history->id, $detailItems);
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

            $shouldRecordFatalDetail = (int) $history->failed_count < 1;
            $history->status = ImportHistory::STATUS_FAILED;
            $history->started_at = $history->started_at ?: now();
            $history->finished_at = now();
            $history->failed_count = max(1, (int) $history->failed_count);
            $history->summary = $this->mergeArray($history->summary, $summary);
            $history->failure_samples = $this->appendSamples($history->failure_samples, $failureSamples);
            $history->error_message = $this->formatErrorMessage($error);
            $history->save();

            if ($shouldRecordFatalDetail) {
                $this->insertDetailItems($history->id, [[
                    'category' => ImportHistoryItem::CATEGORY_FAILED,
                    'message' => $history->error_message,
                ]]);
            }
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
            $isFailed = $status === ImportHistory::STATUS_FAILED || $status === 'failed';
            $shouldRecordFatalDetail = $isFailed && (int) $history->failed_count < 1;

            $history->started_at = $history->started_at ?: now();
            $history->total_rows = (int) ($summary['total_entries'] ?? $history->total_rows);
            $history->success_count = $successCount;
            $history->skipped_count = $skippedCount;
            $history->summary = $this->mergeArray($history->summary, $this->publicMediaSummary($summary));
            $history->failure_samples = $this->appendSamples(
                [],
                $this->mediaFailureSamples($summary['items'] ?? [])
            );

            if ($isFailed) {
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

            if ($shouldRecordFatalDetail) {
                $this->insertDetailItems($history->id, [[
                    'category' => ImportHistoryItem::CATEGORY_FAILED,
                    'message' => $history->error_message,
                ]]);
            }

            if ($isFailed && $skippedCount > 0 && Schema::hasTable('import_history_items')) {
                $storedSkippedCount = ImportHistoryItem::query()
                    ->where('import_history_id', $history->id)
                    ->where('category', ImportHistoryItem::CATEGORY_SKIPPED)
                    ->count();
                $missingSkippedCount = max(0, $skippedCount - $storedSkippedCount);

                if ($missingSkippedCount > 0) {
                    $missingItems = collect($summary['items'] ?? [])
                        ->filter(function ($item) {
                            return is_array($item) && ($item['status'] ?? null) !== 'success';
                        })
                        ->take($missingSkippedCount)
                        ->map(function ($item) {
                            return [
                                'category' => ImportHistoryItem::CATEGORY_SKIPPED,
                                'file' => $item['file'] ?? null,
                                'message' => $item['message'] ?? 'File dilewati.',
                            ];
                        })
                        ->values()
                        ->all();

                    $this->insertDetailItems($history->id, $missingItems);
                }
            }
        });
    }

    public function addDetailItems(?int $historyId, array $items): void
    {
        if (!$this->canWrite($historyId) || empty($items)) {
            return;
        }

        DB::transaction(function () use ($historyId, $items) {
            if (!ImportHistory::whereKey($historyId)->exists()) {
                return;
            }

            $this->insertDetailItems((int) $historyId, $items);
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

    private function insertDetailItems(int $historyId, array $items): void
    {
        if (empty($items) || !Schema::hasTable('import_history_items')) {
            return;
        }

        $allowedCategories = array_keys(ImportHistoryItem::categoryLabels());
        $timestamp = now();
        $rows = [];
        $hasEmployeeNameColumn = Schema::hasColumn('import_history_items', 'employee_name');

        foreach ($items as $item) {
            if (!is_array($item) || !in_array($item['category'] ?? null, $allowedCategories, true)) {
                continue;
            }

            $payload = $this->normalizeArray($item['payload'] ?? []);
            $row = [
                'import_history_id' => $historyId,
                'category' => $item['category'],
                'row_number' => isset($item['row']) ? (int) $item['row'] : null,
                'nik' => isset($item['nik']) ? Str::limit((string) $item['nik'], 100, '') : null,
                'file_name' => isset($item['file']) ? Str::limit((string) $item['file'], 255, '') : null,
                'message' => isset($item['message']) ? Str::limit((string) $item['message'], 500, '') : null,
                'payload' => json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                ),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            if ($hasEmployeeNameColumn) {
                $employeeName = $item['employee_name']
                    ?? $payload['nama_karyawan']
                    ?? $payload['employee_name']
                    ?? $payload['nama']
                    ?? null;
                $row['employee_name'] = $employeeName !== null
                    ? Str::limit(trim((string) $employeeName), 255, '')
                    : null;
            }

            $rows[] = $row;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('import_history_items')->insertOrIgnore($chunk);
        }
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
