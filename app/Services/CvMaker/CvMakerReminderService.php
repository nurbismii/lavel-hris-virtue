<?php

namespace App\Services\CvMaker;

use App\Jobs\SendCvMakerReminderEmail;
use App\Models\CvMakerProgressStatus;
use App\Models\CvMakerReminderBatch;
use App\Models\CvMakerReminderDelivery;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CvMakerReminderService
{
    private CvMakerCompareService $compareService;

    public function __construct(CvMakerCompareService $compareService)
    {
        $this->compareService = $compareService;
    }

    public function createBatch(Request $request, User $actor): CvMakerReminderBatch
    {
        $lock = Cache::lock('cv-maker-reminder:' . $request->input('idempotency_key'), 120);

        if (!$lock->get()) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Request reminder yang sama masih sedang diproses.',
            ]);
        }

        try {
            $existing = CvMakerReminderBatch::query()
            ->where('idempotency_key', $request->input('idempotency_key'))
            ->first();

            if ($existing) {
                if ((string) $existing->requested_by !== (string) $actor->id) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'ID request reminder sudah digunakan.',
                    ]);
                }

                return $existing;
            }

        $filters = $this->normalizedFilters($request);
        $targetNiks = $this->targetNiks($request, $actor, $filters);

        if (empty($targetNiks)) {
            throw ValidationException::withMessages([
                'employee_niks' => 'Tidak ada karyawan dalam scope dan filter yang dipilih.',
            ]);
        }

        $progressByNik = CvMakerProgressStatus::query()
            ->whereIn('employee_nik', $targetNiks)
            ->get()
            ->keyBy('employee_nik');
        $usersByNik = User::query()
            ->whereIn('nik_karyawan', $targetNiks)
            ->orderBy('created_at')
            ->get(['id', 'nik_karyawan', 'email'])
            ->keyBy('nik_karyawan');
        $cooldownDays = max(1, (int) config('services.cv_maker.reminder_cooldown_days', 3));
        $recentlySentNiks = CvMakerReminderDelivery::query()
            ->whereIn('employee_nik', $targetNiks)
            ->where('status', CvMakerReminderDelivery::STATUS_SENT)
            ->where('sent_at', '>=', Carbon::now()->subDays($cooldownDays))
            ->pluck('employee_nik')
            ->flip();

        $batch = DB::transaction(function () use (
            $request,
            $actor,
            $filters,
            $targetNiks,
            $progressByNik,
            $usersByNik,
            $recentlySentNiks,
            $cooldownDays
        ) {
            $batch = CvMakerReminderBatch::create([
                'batch_uuid' => (string) Str::uuid(),
                'idempotency_key' => $request->input('idempotency_key'),
                'requested_by' => $actor->id,
                'selection_mode' => $request->input('selection_mode'),
                'status' => CvMakerReminderBatch::STATUS_QUEUED,
                'total_count' => count($targetNiks),
                'filters' => $filters,
            ]);

            foreach ($targetNiks as $nik) {
                $progress = $progressByNik->get($nik);
                $user = $usersByNik->get($nik);
                $status = CvMakerReminderDelivery::STATUS_PENDING;
                $skipReason = null;

                if (!$progress || !$progress->needs_reminder || !$progress->cv_profile_id) {
                    $status = CvMakerReminderDelivery::STATUS_SKIPPED;
                    $skipReason = 'Status karyawan tidak lagi memerlukan reminder.';
                } elseif (!$user) {
                    $status = CvMakerReminderDelivery::STATUS_SKIPPED;
                    $skipReason = 'Akun HRIS karyawan tidak ditemukan.';
                } elseif (!filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                    $status = CvMakerReminderDelivery::STATUS_SKIPPED;
                    $skipReason = 'Email akun HRIS kosong atau tidak valid.';
                } elseif ($recentlySentNiks->has($nik)) {
                    $status = CvMakerReminderDelivery::STATUS_SKIPPED;
                    $skipReason = 'Reminder sudah dikirim dalam ' . $cooldownDays . ' hari terakhir.';
                }

                CvMakerReminderDelivery::create([
                    'batch_id' => $batch->id,
                    'employee_nik' => $nik,
                    'user_id' => $user ? $user->id : null,
                    'email' => $user ? $user->email : null,
                    'current_step' => $progress ? $progress->current_step : null,
                    'current_step_label' => $progress ? $progress->current_step_label : null,
                    'status' => $status,
                    'skip_reason' => $skipReason,
                    'queued_at' => $status === CvMakerReminderDelivery::STATUS_PENDING ? Carbon::now() : null,
                ]);
            }

            return $this->refreshBatch($batch);
        });

        $delaySeconds = max(0, (int) config('services.cv_maker.reminder_delay_seconds', 2));
        $queue = (string) config('services.cv_maker.reminder_queue', config('queue.connections.database.queue', 'default'));
        $batch->deliveries()
            ->where('status', CvMakerReminderDelivery::STATUS_PENDING)
            ->orderBy('id')
            ->pluck('id')
            ->each(function ($deliveryId, $index) use ($delaySeconds, $queue) {
                SendCvMakerReminderEmail::dispatch((int) $deliveryId)
                    ->onQueue($queue)
                    ->delay(Carbon::now()->addSeconds($delaySeconds * $index));
            });

        app(AuditTrailService::class)->record([
            'event' => 'cv_maker.reminder_batch_queued',
            'module' => 'cv_maker_compare',
            'reference_table' => 'cv_maker_reminder_batches',
            'reference_id' => (string) $batch->id,
            'actor' => $actor,
            'metadata' => [
                'batch_uuid' => $batch->batch_uuid,
                'selection_mode' => $batch->selection_mode,
                'total_count' => $batch->total_count,
                'pending_count' => $batch->pending_count,
                'skipped_count' => $batch->skipped_count,
            ],
            'note' => 'Bulk reminder CV Maker dimasukkan ke antrean.',
        ]);

            return $batch->fresh();
        } finally {
            optional($lock)->release();
        }
    }

    public function refreshBatch(CvMakerReminderBatch $batch): CvMakerReminderBatch
    {
        $counts = $batch->deliveries()
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $pending = (int) ($counts[CvMakerReminderDelivery::STATUS_PENDING] ?? 0);
        $sent = (int) ($counts[CvMakerReminderDelivery::STATUS_SENT] ?? 0);
        $failed = (int) ($counts[CvMakerReminderDelivery::STATUS_FAILED] ?? 0);
        $skipped = (int) ($counts[CvMakerReminderDelivery::STATUS_SKIPPED] ?? 0);
        $processed = $sent + $failed + $skipped;
        $status = $pending > 0
            ? ($processed > 0 ? CvMakerReminderBatch::STATUS_PROCESSING : CvMakerReminderBatch::STATUS_QUEUED)
            : ($failed > 0
                ? ($sent > 0 ? CvMakerReminderBatch::STATUS_PARTIAL_FAILED : CvMakerReminderBatch::STATUS_FAILED)
                : CvMakerReminderBatch::STATUS_COMPLETED);

        $batch->forceFill([
            'status' => $status,
            'pending_count' => $pending,
            'sent_count' => $sent,
            'failed_count' => $failed,
            'skipped_count' => $skipped,
            'started_at' => $processed > 0 ? ($batch->started_at ?: Carbon::now()) : $batch->started_at,
            'finished_at' => $pending === 0 ? Carbon::now() : null,
        ])->save();

        return $batch;
    }

    public function statusPayload(CvMakerReminderBatch $batch): array
    {
        $batch = $this->refreshBatch($batch);
        $processed = $batch->sent_count + $batch->failed_count + $batch->skipped_count;

        return [
            'success' => true,
            'data' => [
                'batch_uuid' => $batch->batch_uuid,
                'status' => $batch->status,
                'total_count' => $batch->total_count,
                'processed_count' => $processed,
                'pending_count' => $batch->pending_count,
                'sent_count' => $batch->sent_count,
                'failed_count' => $batch->failed_count,
                'skipped_count' => $batch->skipped_count,
                'progress' => $batch->total_count > 0 ? (int) floor(($processed / $batch->total_count) * 100) : 100,
                'finished_at' => optional($batch->finished_at)->format('d/m/Y H:i:s'),
            ],
        ];
    }

    private function targetNiks(Request $request, User $actor, array $filters): array
    {
        $filterRequest = Request::create('/cv-maker-compare/reminders', 'GET', array_merge($filters, [
            'search' => ['value' => $filters['search'] ?? ''],
        ]));
        $query = $this->compareService->filteredEmployeeQuery($filterRequest, $actor);

        if ($request->input('selection_mode') === 'selected') {
            $query->whereIn('employees.nik', array_values((array) $request->input('employee_niks', [])));
        }

        $limit = max(1, min((int) config('services.cv_maker.reminder_batch_limit', 500), 1000));
        $niks = $query->reorder('employees.nik')->limit($limit + 1)->pluck('employees.nik')->map(fn($nik) => (string) $nik)->unique()->values();

        if ($niks->count() > $limit) {
            throw ValidationException::withMessages([
                'selection_mode' => 'Hasil filter melebihi batas ' . $limit . ' penerima per batch. Persempit filter terlebih dahulu.',
            ]);
        }

        return $niks->all();
    }

    private function normalizedFilters(Request $request): array
    {
        return [
            'area' => array_values((array) $request->input('area', [])),
            'departemen' => $request->input('departemen'),
            'divisi' => $request->input('divisi'),
            'jabatan' => array_values((array) $request->input('jabatan', [])),
            'status_resign' => $request->input('status_resign', 'AKTIF'),
            'cv_reminder' => 'needs_reminder',
            'cv_progress_status' => $request->input('cv_progress_status'),
            'cv_progress_step' => array_values((array) $request->input('cv_progress_step', [])),
            'cv_review_status' => $request->input('cv_review_status'),
            'search' => trim((string) $request->input('search', '')),
        ];
    }
}
