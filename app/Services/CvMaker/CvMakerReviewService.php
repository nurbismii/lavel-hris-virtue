<?php

namespace App\Services\CvMaker;

use App\Models\CvMakerProgressStatus;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CvMakerReviewService
{
    public function update(string $nik, string $status, ?string $note, User $actor): CvMakerProgressStatus
    {
        return DB::transaction(function () use ($nik, $status, $note, $actor) {
            $progress = CvMakerProgressStatus::query()
                ->where('employee_nik', $nik)
                ->lockForUpdate()
                ->first();

            if (!$progress) {
                throw ValidationException::withMessages([
                    'review_status' => 'Snapshot progress belum tersedia. Jalankan sinkronisasi progress terlebih dahulu.',
                ]);
            }

            $oldValues = [
                'review_status' => $progress->review_status ?: CvMakerProgressStatus::REVIEW_UNREVIEWED,
                'reviewed_by' => $progress->reviewed_by,
                'reviewed_at' => optional($progress->reviewed_at)->toDateTimeString(),
                'review_note' => $progress->review_note,
            ];
            $isUnreviewed = $status === CvMakerProgressStatus::REVIEW_UNREVIEWED;
            $progress->forceFill([
                'review_status' => $status,
                'reviewed_by' => $isUnreviewed ? null : $actor->id,
                'reviewed_at' => $isUnreviewed ? null : Carbon::now(),
                'review_note' => $isUnreviewed ? null : (filled($note) ? trim($note) : null),
            ])->save();

            app(AuditTrailService::class)->record([
                'event' => 'cv_maker.review_status_updated',
                'module' => 'cv_maker_compare',
                'auditable_type' => CvMakerProgressStatus::class,
                'auditable_id' => (string) $progress->id,
                'reference_table' => 'cv_maker_progress_statuses',
                'reference_id' => (string) $progress->id,
                'employee_nik' => $nik,
                'actor' => $actor,
                'old_values' => $oldValues,
                'new_values' => [
                    'review_status' => $progress->review_status,
                    'reviewed_by' => $progress->reviewed_by,
                    'reviewed_at' => optional($progress->reviewed_at)->toDateTimeString(),
                    'review_note' => $progress->review_note,
                ],
                'note' => 'Status pemeriksaan Compare CV Maker diperbarui.',
            ]);

            return $progress;
        });
    }
}
