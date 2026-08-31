<?php

namespace App\Services\Roster;

use App\Models\RosterSchedule;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RosterScheduleManualSubmissionService
{
    private $auditTrail;
    private $actionMutex;

    public function __construct(
        AuditTrailService $auditTrail,
        RosterScheduleActionMutex $actionMutex
    ) {
        $this->auditTrail = $auditTrail;
        $this->actionMutex = $actionMutex;
    }

    public function record(RosterSchedule $schedule, array $data, User $actor): RosterSchedule
    {
        $actionLock = $this->actionMutex->acquire((int) $schedule->getKey());

        if ($actionLock === null) {
            throw ValidationException::withMessages([
                'realization_type' => 'Reminder sedang diproses. Tunggu beberapa saat lalu coba lagi.',
            ]);
        }

        try {
            return DB::transaction(function () use ($schedule, $data, $actor): RosterSchedule {
                $locked = RosterSchedule::query()
                    ->whereKey($schedule->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$locked->is_active || $locked->realization_type !== RosterSchedule::REALIZATION_PENDING) {
                    throw ValidationException::withMessages([
                        'realization_type' => 'Jadwal sudah diproses atau tidak lagi aktif.',
                    ]);
                }

                $hasActiveDigitalApplication = $locked->applications()
                    ->where(function (Builder $query): void {
                        $query->where(function (Builder $hod): void {
                            $hod->whereNull('status_pengajuan')->orWhere('status_pengajuan', '!=', 2);
                        })->where(function (Builder $hrd): void {
                            $hrd->whereNull('status_pengajuan_hrd')->orWhere('status_pengajuan_hrd', '!=', 2);
                        });
                    })
                    ->exists();

                if ($hasActiveDigitalApplication) {
                    throw ValidationException::withMessages([
                        'realization_type' => 'Pengajuan digital sudah tersedia dan tidak boleh ditimpa.',
                    ]);
                }

                $oldValues = $this->auditValues($locked);

                $locked->forceFill([
                    'realization_type' => $data['realization_type'],
                    'manual_submitted_at' => now(),
                    'manual_submitted_by' => $actor->getAuthIdentifier(),
                    'manual_reference_number' => $data['manual_reference_number'] ?? null,
                    'manual_submission_note' => $data['manual_submission_note'] ?? null,
                    'reminder_queued_at' => null,
                    'updated_by' => (string) $actor->getAuthIdentifier(),
                ])->save();

                $updated = $locked->fresh(['manualSubmitter']);

                $audit = $this->auditTrail->record([
                    'event' => 'roster_schedule.manual_submission_recorded',
                    'module' => 'roster_schedule',
                    'auditable_type' => RosterSchedule::class,
                    'auditable_id' => (string) $updated->id,
                    'reference_table' => 'roster_schedules',
                    'reference_id' => (string) $updated->id,
                    'employee_nik' => $updated->employee_nik,
                    'actor' => $actor,
                    'old_values' => $oldValues,
                    'new_values' => $this->auditValues($updated),
                    'metadata' => [
                        'submission_channel' => 'offline',
                        'manual_note_present' => !empty($data['manual_submission_note']),
                    ],
                ]);

                if ($audit === null) {
                    throw new \RuntimeException('Audit pengajuan manual gagal dicatat.');
                }

                return $updated;
            });
        } finally {
            $this->actionMutex->release($actionLock);
        }
    }

    private function auditValues(RosterSchedule $schedule): array
    {
        return [
            'realization_type' => $schedule->realization_type,
            'manual_submitted_at' => optional($schedule->manual_submitted_at)->toDateTimeString(),
            'manual_submitted_by' => $schedule->manual_submitted_by,
            'manual_reference_number' => $schedule->manual_reference_number,
            'reminder_queued_at' => optional($schedule->reminder_queued_at)->toDateTimeString(),
        ];
    }
}
