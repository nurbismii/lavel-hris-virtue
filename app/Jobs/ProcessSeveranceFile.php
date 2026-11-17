<?php

namespace App\Jobs;

use App\Models\ImportHistory;
use App\Services\Severance\SeveranceExport;
use App\Services\Severance\SeveranceService;
use App\Support\SafeExceptionLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProcessSeveranceFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries = 3;
    public $timeout = 300;
    public $backoff = 30;
    public $historyId;

    public function __construct(int $historyId)
    {
        $this->historyId = $historyId;
        $this->onConnection(config('severance.connection', 'database'));
        $this->onQueue(config('severance.queue'));
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('severance-' . $this->historyId))->releaseAfter(30)->expireAfter(360)];
    }

    public function handle(SeveranceService $service, SeveranceExport $export): void
    {
        $history = ImportHistory::whereIn('module', ['severance', 'severance_export'])->find($this->historyId);
        if (!$history || !in_array($history->status, ['queued', 'processing'], true)) { return; }
        $history->update(['status' => 'processing', 'started_at' => $history->started_at ?: now(), 'error_message' => null]);
        try {
            if ($history->module === 'severance') { $service->import($history); }
            else { $export->write($history); }
        } catch (ValidationException $exception) {
            $history->update(['status' => 'failed', 'severance_process_key' => null, 'finished_at' => now(),
                'error_message' => collect($exception->errors())->flatten()->first()]);
        } catch (Throwable $exception) {
            $reference = app(SafeExceptionLogger::class)->warning('severance.file', $exception);
            throw new \RuntimeException('Proses pesangon belum berhasil. Referensi: ' . $reference);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $history = ImportHistory::whereIn('module', ['severance', 'severance_export'])->find($this->historyId);
        if ($history && in_array($history->status, ['queued', 'processing'], true)) {
            $history->update(['status' => 'failed', 'finished_at' => now(),
                'error_message' => 'Proses gagal setelah percobaan ulang. Periksa worker lalu gunakan Coba lagi.']);
        }
    }
}
