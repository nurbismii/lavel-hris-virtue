<?php

namespace App\Services\CvMaker;

use App\Jobs\ProcessCvMakerPdfBatch;
use App\Models\CvMakerPdfBatch;
use App\Models\CvMakerProgressStatus;
use App\Models\Employee;
use App\Models\User;
use App\Services\Storage\SensitiveFileStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;
use ZipArchive;

class CvMakerPdfExportService
{
    public const PREFIX = 'cv-pdf/';
    public const ACTIVE = ['pending', 'processing'];
    public const READY = ['completed', 'partial_failed'];

    public static function canAccess(User $user): bool
    {
        return !$user->hasRole('Audit CV')
            && $user->hasRole(['Super Admin', 'HR', 'HOD', 'Manager', 'Supervisor', 'Admin Divisi'])
            && $user->hasMenuAccess('cv_maker_compare');
    }

    public function create(array $input, User $actor): CvMakerPdfBatch
    {
        abort_unless(self::canAccess($actor), 403);
        $connection = config('cv_pdf.connection');
        if (!in_array(config('queue.connections.' . $connection . '.driver'), ['database', 'redis', 'sqs', 'beanstalkd'], true)) {
            $this->invalid('Queue PDF belum dikonfigurasi. Gunakan koneksi antrean asynchronous.');
        }
        if ($input['mode'] === 'filtered' && !class_exists(ZipArchive::class)) {
            $this->invalid('Ekstensi PHP ZIP diperlukan untuk download batch.');
        }

        $lock = Cache::lock('cv-pdf-create:' . $input['idempotency_key'], 60);
        if (!$lock->get()) {
            $this->invalid('Permintaan yang sama sedang diterima. Cek riwayat PDF sebelum mencoba kembali.');
        }
        try {
            $existing = CvMakerPdfBatch::where('idempotency_key', $input['idempotency_key'])->first();
            if ($existing) {
                abort_unless((string) $existing->requested_by === (string) $actor->id, 403);
                return $existing;
            }
            $batch = DB::transaction(function () use ($input, $actor) {
                if ($input['mode'] === 'single') {
                    $query = $actor->applyCvMakerEmployeeScope(Employee::query(), 'employees')
                        ->where('employees.nik', $input['employee_nik']);
                } else {
                    $filters = $input;
                    $filters['search'] = ['value' => $input['search'] ?? ''];
                    $query = app(CvMakerCompareService::class)->filteredEmployeeQuery(new Request($filters), $actor);
                }
                $query->whereIn('employees.nik', CvMakerProgressStatus::select('employee_nik')
                    ->whereNotNull('cv_profile_id')->where('is_complete', true));
                $query->whereNotExists(function ($q) use ($input) {
                    $q->selectRaw('1')->from('cv_maker_pdf_downloads as pdf')
                        ->whereColumn('pdf.employee_nik', 'employees.nik')
                        ->where(function ($blocked) use ($input) {
                            $blocked->whereNotNull('pdf.active_batch_id');
                            if (empty($input['allow_downloaded'])) {
                                $blocked->orWhereNotNull('pdf.downloaded_at');
                            }
                        });
                });
                $limit = max(1, min(100, (int) config('cv_pdf.batch_limit', 50)));
                // Take the next bounded batch. Reservations/downloads remove earlier batches
                // from subsequent requests even when the original filter matches thousands.
                $niks = $query->select('employees.nik')->reorder('employees.nik')->limit($limit)->toBase()->pluck('employees.nik');
                // Progress rows already exist for every eligible employee and serialize competing batches.
                $eligible = CvMakerProgressStatus::whereIn('employee_nik', $niks)
                    ->whereNotNull('cv_profile_id')->where('is_complete', true)
                    ->orderBy('employee_nik')->lockForUpdate()->pluck('employee_nik');
                $batch = CvMakerPdfBatch::create([
                    'uuid' => (string) Str::uuid(), 'idempotency_key' => $input['idempotency_key'],
                    'requested_by' => $actor->id, 'mode' => $input['mode'], 'status' => 'pending',
                    'filters' => $input, 'expires_at' => now()->addDays(max(1, (int) config('cv_pdf.retention_days', 7))),
                ]);
                foreach ($eligible as $nik) {
                    DB::table('cv_maker_pdf_downloads')->insertOrIgnore([
                        'employee_nik' => $nik, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $claim = DB::table('cv_maker_pdf_downloads')->where('employee_nik', $nik)->lockForUpdate()->first();
                    if ($claim->active_batch_id || (empty($input['allow_downloaded']) && $claim->downloaded_at)) {
                        continue;
                    }
                    $batch->items()->create(['employee_nik' => $nik, 'status' => 'pending']);
                    DB::table('cv_maker_pdf_downloads')->where('employee_nik', $nik)
                        ->update(['active_batch_id' => $batch->id, 'updated_at' => now()]);
                }
                $batch->total_count = $batch->items()->count();
                if (!$batch->total_count) {
                    $this->invalid('Tidak ada CV lengkap yang dapat diproses. CV mungkin sudah diunduh atau sedang berada dalam batch lain.');
                }
                $batch->save();
                return $batch;
            }, 3);
            // Commit first. A scheduler recovers a committed request if dispatch is interrupted.
            $this->dispatch($batch);
            return $batch;
        } finally {
            $lock->release();
        }
    }

    public function dispatch(CvMakerPdfBatch $batch): void
    {
        try {
            if (!in_array(config('queue.connections.' . config('cv_pdf.connection') . '.driver'), ['database', 'redis', 'sqs', 'beanstalkd'], true)) {
                throw new RuntimeException('Koneksi antrean PDF harus asynchronous.');
            }
            ProcessCvMakerPdfBatch::dispatch($batch->id)
                ->onConnection(config('cv_pdf.connection'))->onQueue(config('cv_pdf.queue'));
        } catch (Throwable $e) {
            Log::warning('CV PDF dispatch deferred to scheduler.', ['batch_id' => $batch->id, 'exception' => get_class($e)]);
            $batch->update(['error_message' => 'Antrean belum menerima proses. Scheduler akan mencoba kembali.']);
        }
    }

    public function processNext(int $batchId): void
    {
        $lock = Cache::lock('cv-pdf-batch:' . $batchId, 240);
        if (!$lock->get()) {
            return;
        }
        $continue = false;
        try {
            $batch = CvMakerPdfBatch::find($batchId);
            if (!$batch || !in_array($batch->status, self::ACTIVE, true)) {
                return;
            }
            if ($batch->expires_at->isPast()) {
                $this->close($batch, 'expired', 'Masa penyimpanan PDF telah berakhir.');
                return;
            }
            $actor = User::find($batch->requested_by);
            if (!$actor || !self::canAccess($actor)) {
                $this->close($batch, 'failed', 'Hak akses pemohon sudah tidak berlaku.');
                return;
            }
            $batch->update(['status' => 'processing', 'started_at' => $batch->started_at ?: now(), 'error_message' => null]);
            // A crashed worker leaves processing behind. The lock TTL is longer than the job timeout.
            $item = $batch->items()->whereIn('status', ['pending', 'processing'])->orderBy('id')->first();
            if ($item) {
                $item->update(['status' => 'processing']);
                try {
                    $employee = $actor->applyCvMakerEmployeeScope(Employee::query(), 'employees')
                        ->where('employees.nik', $item->employee_nik)->first();
                    if (!$employee || !CvMakerProgressStatus::where('employee_nik', $item->employee_nik)
                        ->whereNotNull('cv_profile_id')->where('is_complete', true)->exists()) {
                        throw new RuntimeException('CV tidak lagi lengkap atau di luar akses pemohon.');
                    }
                    if ($item->attempts >= 3) {
                        throw new RuntimeException('Batas percobaan PDF tercapai.');
                    }
                    $item->increment('attempts');
                    $path = app(CvMakerPdfRenderer::class)->render($employee, $batch->uuid, $item->id);
                    $item->update(['status' => 'completed', 'file_path' => $path, 'error_message' => null]);
                } catch (Throwable $e) {
                    Log::warning('CV PDF item failed.', ['batch_id' => $batchId, 'item_id' => $item->id, 'exception' => get_class($e)]);
                    $item->update(['status' => 'failed', 'error_message' => 'PDF gagal dibuat. Periksa kelengkapan CV dan koneksi CV Maker, lalu ajukan kembali.']);
                    $this->releaseClaims($batch, $item->employee_nik);
                }
                $batch->touch();
                $continue = true;
            } else {
                if ($batch->packaging_attempts >= 3) {
                    $this->close($batch, 'failed', 'Pengemasan PDF melewati batas percobaan. Kurangi ukuran batch lalu ajukan kembali.');
                } else {
                    $batch->increment('packaging_attempts');
                    $this->finish($batch);
                }
            }
        } finally {
            $lock->release();
        }
        if ($continue) {
            $this->dispatch($batch);
        }
    }

    private function finish(CvMakerPdfBatch $batch): void
    {
        DB::table('cv_maker_pdf_downloads')->where('active_batch_id', $batch->id)
            ->whereIn('employee_nik', $batch->items()->where('status', 'failed')->select('employee_nik'))
            ->update(['active_batch_id' => null, 'updated_at' => now()]);
        $items = $batch->items()->where('status', 'completed')->orderBy('id')->get();
        if ($items->isEmpty()) {
            $this->close($batch, 'failed', 'Semua PDF gagal dibuat. Periksa CV Maker lalu ajukan kembali.');
            return;
        }
        $storage = app(SensitiveFileStorageService::class);
        if ($batch->mode === 'single') {
            $path = $items->first()->file_path;
        } else {
            $path = self::PREFIX . $batch->uuid . '/batch.zip';
            $directory = $storage->ensurePrivateDirectory(self::PREFIX . $batch->uuid);
            $temporary = $directory . '/batch.tmp.zip';
            $zip = new ZipArchive();
            if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Arsip PDF gagal dibuka.');
            }
            $bytes = 0;
            try {
                foreach ($items as $item) {
                    $source = $storage->resolvePrivatePath($item->file_path, [self::PREFIX]);
                    if (!$source) {
                        throw new RuntimeException('File PDF batch tidak tersedia.');
                    }
                    $bytes += filesize($source);
                    if ($bytes > (int) config('cv_pdf.max_archive_bytes', 104857600)) {
                        throw new RuntimeException('Ukuran batch melebihi batas.');
                    }
                    $filename = 'CV-' . Str::slug($item->employee_nik) . '-' . $item->id . '.pdf';
                    if (!$zip->addFile($source, $filename)) {
                        throw new RuntimeException('File gagal dimasukkan ke arsip.');
                    }
                    $zip->setCompressionName($filename, ZipArchive::CM_STORE);
                }
            } catch (Throwable $e) {
                $zip->close();
                throw $e;
            }
            if (!$zip->close() || !rename($temporary, $directory . '/batch.zip')) {
                throw new RuntimeException('Arsip PDF gagal disimpan.');
            }
        }
        $batch->update([
            'status' => $items->count() === $batch->total_count ? 'completed' : 'partial_failed',
            'result_path' => $path, 'finished_at' => now(), 'error_message' => null,
        ]);
    }

    public function fail(int $batchId): void
    {
        $lock = Cache::lock('cv-pdf-batch:' . $batchId, 240);
        if (!$lock->get()) return;
        try {
            $batch = CvMakerPdfBatch::find($batchId);
            if ($batch && in_array($batch->status, self::ACTIVE, true)) {
                $this->close($batch, 'failed', 'Proses PDF gagal. Persempit batch atau periksa worker, lalu ajukan kembali.');
            }
        } finally {
            $lock->release();
        }
    }

    public function close(CvMakerPdfBatch $batch, string $status, string $message): void
    {
        DB::transaction(function () use ($batch, $status, $message) {
            $batch->update(['status' => $status, 'error_message' => $message, 'finished_at' => now()]);
            $batch->items()->whereIn('status', ['pending', 'processing'])
                ->update(['status' => 'failed', 'error_message' => $message, 'updated_at' => now()]);
            $this->releaseClaims($batch);
        });
    }

    public function releaseClaims(CvMakerPdfBatch $batch, ?string $nik = null): void
    {
        $query = DB::table('cv_maker_pdf_downloads')->where('active_batch_id', $batch->id);
        if ($nik !== null) $query->where('employee_nik', $nik);
        $query->update(['active_batch_id' => null, 'updated_at' => now()]);
    }

    public function download(CvMakerPdfBatch $batch, User $actor)
    {
        abort_unless(self::canAccess($actor) && (string) $batch->requested_by === (string) $actor->id, 403);
        $lock = Cache::lock('cv-pdf-batch:' . $batch->id, 240);
        if (!$lock->get()) $this->invalid('Batch sedang diproses. Silakan coba kembali.');
        try {
            $batch->refresh();
            abort_unless(in_array($batch->status, self::READY, true), 409, 'PDF belum tersedia.');
            abort_if($batch->expires_at->isPast(), 410, 'File PDF sudah kedaluwarsa. Ajukan batch baru.');
            $niks = $batch->items()->where('status', 'completed')->pluck('employee_nik');
            $accessible = $actor->applyCvMakerEmployeeScope(Employee::query(), 'employees')
                ->whereIn('employees.nik', $niks)
                ->whereIn('employees.nik', CvMakerProgressStatus::select('employee_nik')
                    ->whereNotNull('cv_profile_id')->where('is_complete', true))->count();
            abort_unless($accessible === $niks->count(), 403, 'Sebagian CV tidak lagi lengkap atau berada di luar hak akses. Batalkan batch dan ajukan kembali.');
            $path = app(SensitiveFileStorageService::class)->resolvePrivatePath($batch->result_path, [self::PREFIX]);
            abort_unless($path && is_readable($path), 410, 'File PDF tidak tersedia. Batalkan batch dan ajukan kembali.');
            $response = response()->download($path, 'CV-' . $batch->uuid . ($batch->mode === 'single' ? '.pdf' : '.zip'), [
                'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            ]);
            DB::transaction(function () use ($batch, $actor, $niks) {
                DB::table('cv_maker_pdf_downloads')->whereIn('employee_nik', $niks)->update([
                    'downloaded_at' => now(), 'downloaded_by' => $actor->id,
                    'last_batch_id' => $batch->id, 'updated_at' => now(),
                ]);
                $this->releaseClaims($batch);
                $batch->update(['downloaded_at' => now()]);
            });
            return $response;
        } finally {
            $lock->release();
        }
    }

    public function payload(CvMakerPdfBatch $batch): array
    {
        if (array_key_exists('success_count', $batch->getAttributes())) {
            $success = (int) $batch->success_count;
            $failed = (int) $batch->failed_count;
        } else {
            $counts = $batch->items()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
            $success = (int) ($counts['completed'] ?? 0);
            $failed = (int) ($counts['failed'] ?? 0);
        }
        return [
            'uuid' => $batch->uuid, 'mode' => $batch->mode, 'status' => $batch->status,
            'total' => $batch->total_count, 'success' => $success, 'failed' => $failed,
            'processed' => $success + $failed, 'error_message' => $batch->error_message,
            'created_at' => $batch->created_at->format('d/m/Y H:i'),
            'started_at' => optional($batch->started_at)->format('d/m/Y H:i'),
            'finished_at' => optional($batch->finished_at)->format('d/m/Y H:i'),
            'expires_at' => $batch->expires_at->format('d/m/Y H:i'),
            'downloaded_at' => optional($batch->downloaded_at)->format('d/m/Y H:i'),
            'download_url' => in_array($batch->status, self::READY, true) && $batch->expires_at->isFuture()
                ? route('cv-maker-compare.pdf.download', $batch) : null,
            'status_url' => route('cv-maker-compare.pdf.status', $batch),
            'cancel_url' => route('cv-maker-compare.pdf.cancel', $batch),
        ];
    }

    private function invalid(string $message): void
    {
        throw ValidationException::withMessages(['pdf' => $message]);
    }
}
