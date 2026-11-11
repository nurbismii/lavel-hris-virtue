<?php

namespace App\Jobs;

use App\Models\ImportHistory;
use App\Services\Payroll\PayrollService;
use App\Services\Storage\SensitiveFileStorageService;
use App\Support\SafeExceptionLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProcessPayrollImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries=3;
    public $timeout=600;
    public $backoff=30;
    public $historyId;

    public function __construct(int $historyId)
    {
        $this->historyId=$historyId;
        $this->onConnection(config('payroll.connection','database'));
        $this->onQueue(config('payroll.queue'));
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('payroll-'.$this->historyId))->releaseAfter(30)->expireAfter(660)];
    }

    public function handle(PayrollService $service): void
    {
        $history=ImportHistory::where('import_type',ImportHistory::TYPE_PAYROLL)->find($this->historyId);
        if (!$history || !in_array($history->status,[ImportHistory::STATUS_QUEUED,ImportHistory::STATUS_PROCESSING],true)) { return; }
        $history->update(['status'=>ImportHistory::STATUS_PROCESSING,'started_at'=>$history->started_at ?: now()]);
        try { $service->preview($history); }
        catch (ValidationException $e) {
            $history->update(['status'=>ImportHistory::STATUS_VALIDATION_FAILED,'payroll_upload_key'=>null,
                'finished_at'=>now(),'error_message'=>collect($e->errors())->flatten()->first()]);
            if ($history->file_path) { app(SensitiveFileStorageService::class)->deletePrivate($history->file_path,['payroll/']); }
        } catch (Throwable $e) {
            $id=app(SafeExceptionLogger::class)->warning('payroll.calculate',$e);
            throw new \RuntimeException('Perhitungan payroll gagal. Referensi: '.$id);
        }
    }

    public function failed(?Throwable $e): void
    {
        $history=ImportHistory::where('import_type',ImportHistory::TYPE_PAYROLL)->find($this->historyId);
        if (!$history || !in_array($history->status,[ImportHistory::STATUS_QUEUED,ImportHistory::STATUS_PROCESSING],true)) { return; }
        $history->update(['status'=>ImportHistory::STATUS_FAILED,'payroll_upload_key'=>null,'finished_at'=>now(),
            'error_message'=>'Perhitungan gagal setelah percobaan ulang. Minta admin memeriksa antrean, lalu hitung ulang.']);
        if ($history->file_path) { app(SensitiveFileStorageService::class)->deletePrivate($history->file_path,['payroll/']); }
    }
}
