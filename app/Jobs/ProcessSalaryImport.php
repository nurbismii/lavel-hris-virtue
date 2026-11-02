<?php

namespace App\Jobs;

use App\Models\ImportHistory;
use App\Services\Salary\SalaryImportService;
use App\Services\Storage\SensitiveFileStorageService;
use App\Support\SafeExceptionLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessSalaryImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 600;
    public $backoff = 30;
    public $historyId;
    public $phase;

    public function __construct(int $historyId, string $phase = 'preview')
    {
        $this->historyId = $historyId;
        $this->phase = $phase;
        $this->onConnection(config('salary.import.connection', 'database'));
        $this->onQueue(config('salary.import.queue', 'default'));
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('salary-import-' . $this->historyId))->releaseAfter(30)->expireAfter(660)];
    }

    public function handle(SalaryImportService $service): void
    {
        $history = ImportHistory::where('import_type', ImportHistory::TYPE_EMPLOYEE_SALARY)->find($this->historyId);
        if (!$history || !in_array($history->status, [ImportHistory::STATUS_QUEUED, ImportHistory::STATUS_PROCESSING], true)
            || ($history->summary['phase'] ?? null) !== $this->phase) {
            return;
        }
        $history->update(['status' => ImportHistory::STATUS_PROCESSING, 'started_at' => $history->started_at ?: now()]);
        try {
            if ($this->phase === 'preview') {
                $service->preview($history);
            } else {
                $service->commit($history);
            }
        } catch (Throwable $exception) {
            if ($exception instanceof \Illuminate\Validation\ValidationException) {
                $history->update(['status' => ImportHistory::STATUS_VALIDATION_FAILED, 'salary_upload_key' => null,
                    'finished_at' => now(), 'error_message' => collect($exception->errors())->flatten()->first()]);
                app(SensitiveFileStorageService::class)->deletePrivate((string) $history->file_path, ['salary-imports/']);
                return;
            }
            $context = app(SafeExceptionLogger::class)->warning('salary_import.' . $this->phase, $exception);
            // Queue logs must not retain SQL bindings or document contents from the original exception.
            throw new \RuntimeException('Pemrosesan pembaruan gaji gagal. Referensi: ' . $context);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $history = ImportHistory::where('import_type', ImportHistory::TYPE_EMPLOYEE_SALARY)->find($this->historyId);
        if (!$history || in_array($history->status, [ImportHistory::STATUS_COMPLETED, ImportHistory::STATUS_COMPLETED_WITH_ERRORS], true)) {
            return;
        }
        $history->update(['status' => ImportHistory::STATUS_FAILED, 'finished_at' => now(), 'salary_upload_key' => null,
            'error_message' => 'Pemrosesan gaji gagal. Periksa baris yang sudah diterapkan sebelum mengunggah ulang baris yang belum berhasil.']);
        app(SensitiveFileStorageService::class)->deletePrivate((string) $history->file_path, ['salary-imports/']);
    }
}
