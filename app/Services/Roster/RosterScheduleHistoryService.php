<?php

namespace App\Services\Roster;

use App\Models\RosterSchedule;
use App\Models\RosterScheduleHistory;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use Illuminate\Support\Facades\DB;

class RosterScheduleHistoryService
{
    public function __construct(private readonly AuditTrailService $auditTrail)
    {
    }

    public function confirm(RosterScheduleHistory $history, array $data, User $actor): RosterScheduleHistory
    {
        $oldValues = $history->only(['classification', 'review_status', 'review_note']);

        $history = DB::transaction(function () use ($history, $data, $actor) {
            $lockedHistory = RosterScheduleHistory::query()
                ->with('schedule')
                ->lockForUpdate()
                ->findOrFail($history->id);

            $lockedHistory->update([
                'classification' => $data['classification'],
                'review_status' => RosterScheduleHistory::REVIEW_CONFIRMED,
                'review_note' => $data['review_note'],
                'reviewed_at' => now(),
                'reviewed_by' => (string) $actor->getAuthIdentifier(),
            ]);

            if ($lockedHistory->schedule && $lockedHistory->schedule->source === RosterSchedule::SOURCE_IMPORT) {
                $realization = $this->scheduleRealization($data['classification']);

                if ($realization) {
                    $lockedHistory->schedule->update([
                        'realization_type' => $realization,
                        'updated_by' => (string) $actor->getAuthIdentifier(),
                    ]);
                }
            }

            return $lockedHistory->fresh(['employee', 'schedule']);
        });

        $this->auditTrail->record([
            'event' => 'roster_schedule_history.reviewed',
            'module' => 'roster_schedule',
            'auditable_type' => RosterScheduleHistory::class,
            'auditable_id' => (string) $history->id,
            'reference_table' => 'roster_schedule_histories',
            'reference_id' => (string) $history->id,
            'employee_nik' => $history->employee_nik,
            'actor' => $actor,
            'old_values' => $oldValues,
            'new_values' => $history->only(['classification', 'review_status', 'review_note']),
        ]);

        return $history;
    }

    private function scheduleRealization(string $classification): ?string
    {
        if ($classification === RosterScheduleHistory::CLASSIFICATION_CUTI) {
            return RosterSchedule::REALIZATION_CUTI;
        }

        if ($classification === RosterScheduleHistory::CLASSIFICATION_INSENTIF) {
            return RosterSchedule::REALIZATION_INSENTIF;
        }

        if (in_array($classification, [
            RosterScheduleHistory::CLASSIFICATION_PLANNED,
            RosterScheduleHistory::CLASSIFICATION_NOT_APPLICABLE,
        ], true)) {
            return RosterSchedule::REALIZATION_PENDING;
        }

        return null;
    }
}
