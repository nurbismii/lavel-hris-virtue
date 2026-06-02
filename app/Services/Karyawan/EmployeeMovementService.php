<?php

namespace App\Services\Karyawan;

use App\Models\ApprovalDelegation;
use App\Models\Divisi;
use App\Models\Employee;
use App\Models\EmployeeMovement;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class EmployeeMovementService
{
    public function submit(array $data, User $actor): EmployeeMovement
    {
        return DB::transaction(function () use ($data, $actor) {
            $employee = Employee::query()
                ->whereKey($data['employee_nik'])
                ->lockForUpdate()
                ->firstOrFail();

            if (!$this->canSubmitForEmployee($employee, $actor)) {
                throw ValidationException::withMessages([
                    'employee_nik' => 'Karyawan tidak tersedia dalam scope pengajuan Anda.',
                ]);
            }

            $movementType = $data['movement_type'];
            $oldValues = $this->snapshot($employee);
            $targetValues = $this->targetValues($movementType, $data, $employee);

            $this->guardRealChange($movementType, $oldValues, $targetValues);

            $autoHodApproved = $this->submissionCountsAsHodApproval($employee, $actor);

            $movement = EmployeeMovement::create([
                'employee_nik' => $employee->nik,
                'movement_type' => $movementType,
                'effective_date' => $data['effective_date'],
                'status' => $autoHodApproved
                    ? EmployeeMovement::STATUS_PENDING_HRD
                    : EmployeeMovement::STATUS_PENDING_HOD,
                'old_posisi' => $oldValues['posisi'],
                'new_posisi' => $targetValues['posisi'],
                'old_jabatan' => $oldValues['jabatan'],
                'new_jabatan' => $targetValues['jabatan'],
                'old_departemen_id' => $oldValues['departemen_id'],
                'new_departemen_id' => $targetValues['departemen_id'],
                'old_divisi_id' => $oldValues['divisi_id'],
                'new_divisi_id' => $targetValues['divisi_id'],
                'reference_number' => $data['reference_number'] ?? null,
                'reason' => $data['reason'],
                'created_by_user_id' => (string) $actor->id,
                'hod_status' => $autoHodApproved
                    ? EmployeeMovement::APPROVAL_APPROVED
                    : EmployeeMovement::APPROVAL_PENDING,
                'hod_processed_by' => $autoHodApproved ? (string) $actor->id : null,
                'hod_processed_at' => $autoHodApproved ? now() : null,
                'hrd_status' => EmployeeMovement::APPROVAL_PENDING,
            ]);

            $this->recordMovementAudit(
                'employee.movement.submitted',
                $movement,
                $actor,
                $oldValues,
                $targetValues,
                $data['reason'],
                [
                    'movement_type' => $movementType,
                    'effective_date' => $data['effective_date'],
                    'reference_number' => $data['reference_number'] ?? null,
                    'hod_auto_approved' => $autoHodApproved,
                ]
            );

            return $this->freshMovement($movement);
        });
    }

    public function processHod(EmployeeMovement $movement, User $actor, int $action, ?string $note = null): EmployeeMovement
    {
        return DB::transaction(function () use ($movement, $actor, $action, $note) {
            $movement = EmployeeMovement::query()
                ->whereKey($movement->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (!$this->canProcessHod($movement, $actor)) {
                throw ValidationException::withMessages([
                    'action' => 'Pengajuan ini tidak tersedia dalam scope approval HOD Anda.',
                ]);
            }

            if (!$movement->isPendingHod()) {
                throw ValidationException::withMessages([
                    'action' => 'Pengajuan sudah diproses atau tidak lagi menunggu approval HOD.',
                ]);
            }

            $approved = $action === EmployeeMovement::APPROVAL_APPROVED;
            $oldApprovalValues = $this->approvalSnapshot($movement);

            $movement->forceFill([
                'hod_status' => $action,
                'hod_processed_by' => (string) $actor->id,
                'hod_processed_at' => now(),
                'hod_rejection_reason' => $approved ? null : $note,
                'status' => $approved
                    ? EmployeeMovement::STATUS_PENDING_HRD
                    : EmployeeMovement::STATUS_REJECTED,
            ])->save();

            $movement = $movement->fresh();

            $this->recordMovementAudit(
                $approved ? 'employee.movement.hod.approved' : 'employee.movement.hod.rejected',
                $movement,
                $actor,
                $oldApprovalValues,
                $this->approvalSnapshot($movement),
                $note,
                ['stage' => 'hod', 'action' => $action]
            );

            return $this->freshMovement($movement);
        });
    }

    public function processHrd(EmployeeMovement $movement, User $actor, int $action, ?string $note = null): EmployeeMovement
    {
        return DB::transaction(function () use ($movement, $actor, $action, $note) {
            $movement = EmployeeMovement::query()
                ->whereKey($movement->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (!$this->canProcessHrd($movement, $actor)) {
                throw ValidationException::withMessages([
                    'action' => 'Anda tidak memiliki akses approval HRD untuk pengajuan ini.',
                ]);
            }

            if (!$movement->isPendingHrd()) {
                throw ValidationException::withMessages([
                    'action' => 'Pengajuan sudah diproses atau belum disetujui HOD.',
                ]);
            }

            $oldApprovalValues = $this->approvalSnapshot($movement);

            if ($action === EmployeeMovement::APPROVAL_REJECTED) {
                $movement->forceFill([
                    'hrd_status' => EmployeeMovement::APPROVAL_REJECTED,
                    'hrd_processed_by' => (string) $actor->id,
                    'hrd_processed_at' => now(),
                    'hrd_rejection_reason' => $note,
                    'status' => EmployeeMovement::STATUS_REJECTED,
                ])->save();

                $movement = $movement->fresh();

                $this->recordMovementAudit(
                    'employee.movement.hrd.rejected',
                    $movement,
                    $actor,
                    $oldApprovalValues,
                    $this->approvalSnapshot($movement),
                    $note,
                    ['stage' => 'hrd', 'action' => $action]
                );

                return $this->freshMovement($movement);
            }

            $employee = Employee::query()
                ->whereKey($movement->employee_nik)
                ->lockForUpdate()
                ->firstOrFail();

            $currentValues = $this->snapshot($employee);
            $expectedOldValues = $this->oldValuesFromMovement($movement);
            $targetValues = $this->targetValuesFromMovement($movement);

            if ($this->hasChangedSinceSubmission($expectedOldValues, $currentValues)) {
                throw ValidationException::withMessages([
                    'action' => 'Data karyawan sudah berubah sejak pengajuan dibuat. Silakan review ulang dan buat pengajuan baru agar master karyawan tidak tertimpa data lama.',
                ]);
            }

            if ($movement->effective_date && $movement->effective_date->isAfter(today())) {
                $movement->forceFill([
                    'hrd_status' => EmployeeMovement::APPROVAL_APPROVED,
                    'hrd_processed_by' => (string) $actor->id,
                    'hrd_processed_at' => now(),
                    'hrd_rejection_reason' => null,
                    'status' => EmployeeMovement::STATUS_SCHEDULED,
                    'application_attempted_at' => null,
                    'application_error' => null,
                ])->save();

                $movement = $movement->fresh();

                $this->recordMovementAudit(
                    'employee.movement.hrd.approved',
                    $movement,
                    $actor,
                    $oldApprovalValues,
                    $this->approvalSnapshot($movement),
                    $note,
                    ['stage' => 'hrd', 'action' => $action, 'scheduled' => true]
                );

                $this->recordMovementAudit(
                    'employee.movement.scheduled',
                    $movement,
                    $actor,
                    $expectedOldValues,
                    $targetValues,
                    $movement->reason,
                    [
                        'movement_type' => $movement->movement_type,
                        'effective_date' => optional($movement->effective_date)->toDateString(),
                        'reference_number' => $movement->reference_number,
                    ]
                );

                return $this->freshMovement($movement);
            }

            $employee->forceFill([
                'posisi' => $targetValues['posisi'],
                'jabatan' => $targetValues['jabatan'],
                'departemen_id' => $targetValues['departemen_id'],
                'divisi_id' => $targetValues['divisi_id'],
            ])->save();

            $movement->forceFill([
                'hrd_status' => EmployeeMovement::APPROVAL_APPROVED,
                'hrd_processed_by' => (string) $actor->id,
                'hrd_processed_at' => now(),
                'hrd_rejection_reason' => null,
                'status' => EmployeeMovement::STATUS_APPROVED,
                'applied_by_user_id' => (string) $actor->id,
                'applied_at' => now(),
                'application_attempted_at' => now(),
                'application_error' => null,
            ])->save();

            $movement = $movement->fresh();

            $this->recordMovementAudit(
                'employee.movement.hrd.approved',
                $movement,
                $actor,
                $oldApprovalValues,
                $this->approvalSnapshot($movement),
                $note,
                ['stage' => 'hrd', 'action' => $action]
            );

            $this->recordMovementAudit(
                'employee.movement.applied',
                $movement,
                $actor,
                $expectedOldValues,
                $targetValues,
                $movement->reason,
                [
                    'movement_type' => $movement->movement_type,
                    'effective_date' => optional($movement->effective_date)->toDateString(),
                    'reference_number' => $movement->reference_number,
                ]
            );

            return $this->freshMovement($movement);
        });
    }

    public function applyDueMovements(int $limit = 200): array
    {
        $limit = max(1, min($limit, 1000));
        $summary = [
            'checked' => 0,
            'applied' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        EmployeeMovement::query()
            ->where('status', EmployeeMovement::STATUS_SCHEDULED)
            ->where('hod_status', EmployeeMovement::APPROVAL_APPROVED)
            ->where('hrd_status', EmployeeMovement::APPROVAL_APPROVED)
            ->whereNull('applied_at')
            ->whereDate('effective_date', '<=', today())
            ->orderBy('effective_date')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (EmployeeMovement $movement) use (&$summary) {
                $summary['checked']++;

                $freshMovement = $this->applyDueMovement($movement);

                if ($freshMovement->status === EmployeeMovement::STATUS_APPROVED) {
                    $summary['applied']++;
                    return;
                }

                if ($freshMovement->status === EmployeeMovement::STATUS_APPLY_FAILED) {
                    $summary['failed']++;
                    return;
                }

                $summary['skipped']++;
            });

        return $summary;
    }

    public function applyDueMovement(EmployeeMovement $movement, ?User $actor = null): EmployeeMovement
    {
        return DB::transaction(function () use ($movement, $actor) {
            $movement = EmployeeMovement::query()
                ->whereKey($movement->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (!$movement->isApprovedPendingEffective()) {
                return $this->freshMovement($movement);
            }

            if ($movement->effective_date && $movement->effective_date->isAfter(today())) {
                return $this->freshMovement($movement);
            }

            $employee = Employee::query()
                ->whereKey($movement->employee_nik)
                ->lockForUpdate()
                ->firstOrFail();

            $currentValues = $this->snapshot($employee);
            $expectedOldValues = $this->oldValuesFromMovement($movement);
            $targetValues = $this->targetValuesFromMovement($movement);

            if ($this->hasChangedSinceSubmission($expectedOldValues, $currentValues)) {
                $message = 'Data karyawan sudah berubah sebelum tanggal efektif. Pengajuan perlu direview ulang oleh HRD.';

                $movement->forceFill([
                    'status' => EmployeeMovement::STATUS_APPLY_FAILED,
                    'application_attempted_at' => now(),
                    'application_error' => $message,
                ])->save();

                $movement = $movement->fresh();

                $this->recordMovementAudit(
                    'employee.movement.apply_failed',
                    $movement,
                    $actor,
                    $expectedOldValues,
                    $currentValues,
                    $message,
                    [
                        'movement_type' => $movement->movement_type,
                        'effective_date' => optional($movement->effective_date)->toDateString(),
                        'reason' => 'stale_employee_snapshot',
                    ]
                );

                return $this->freshMovement($movement);
            }

            $employee->forceFill([
                'posisi' => $targetValues['posisi'],
                'jabatan' => $targetValues['jabatan'],
                'departemen_id' => $targetValues['departemen_id'],
                'divisi_id' => $targetValues['divisi_id'],
            ])->save();

            $movement->forceFill([
                'status' => EmployeeMovement::STATUS_APPROVED,
                'applied_by_user_id' => $actor ? (string) $actor->id : null,
                'applied_at' => now(),
                'application_attempted_at' => now(),
                'application_error' => null,
            ])->save();

            $movement = $movement->fresh();

            $this->recordMovementAudit(
                'employee.movement.applied',
                $movement,
                $actor,
                $expectedOldValues,
                $targetValues,
                $movement->reason,
                [
                    'movement_type' => $movement->movement_type,
                    'effective_date' => optional($movement->effective_date)->toDateString(),
                    'reference_number' => $movement->reference_number,
                    'trigger' => 'scheduled_command',
                ]
            );

            return $this->freshMovement($movement);
        });
    }

    public function canAccessMovementModule(User $actor): bool
    {
        return $actor->hasMenuAccess('employee_movement')
            || $this->hasActiveMovementDelegation($actor);
    }

    public function canSubmitForEmployee(Employee $employee, User $actor): bool
    {
        if ($actor->canAccessAllEmployees()) {
            return true;
        }

        if ($actor->hasRole('HOD') && $this->scopeAllowsEmployee($employee, $actor)) {
            return true;
        }

        return $this->hasActiveMovementDelegationForEmployee($employee, $actor);
    }

    public function canProcessHod(EmployeeMovement $movement, User $actor): bool
    {
        if ($actor->hasRole('Super Admin')) {
            return true;
        }

        return $actor->hasRole('HOD')
            && $this->scopeAllowsSnapshot($movement->old_departemen_id, $movement->old_divisi_id, $actor);
    }

    public function canProcessHrd(EmployeeMovement $movement, User $actor): bool
    {
        return $actor->hasRole(['Super Admin', 'HR'])
            && $actor->hasMenuAccess('approval_hr');
    }

    public function scopeEmployeesForSubmission(Builder $query, User $actor, string $table = 'employees'): Builder
    {
        if ($actor->canAccessAllEmployees()) {
            return $query;
        }

        return $query->where(function (Builder $scopeQuery) use ($actor, $table) {
            $hasCondition = false;

            if ($actor->hasRole('HOD')) {
                $departemenIds = $actor->scopedDepartmentIds();
                $divisiIds = $actor->scopedDivisionIds();

                if (!empty($departemenIds) || !empty($divisiIds)) {
                    $scopeQuery->where(function (Builder $hodQuery) use ($departemenIds, $divisiIds, $table) {
                        if (!empty($departemenIds)) {
                            $hodQuery->whereIn($table . '.departemen_id', $departemenIds);
                        }

                        if (!empty($divisiIds)) {
                            $method = !empty($departemenIds) ? 'orWhereIn' : 'whereIn';
                            $hodQuery->{$method}($table . '.divisi_id', $divisiIds);
                        }
                    });

                    $hasCondition = true;
                }
            }

            if ($this->hasActiveMovementDelegation($actor)) {
                $method = $hasCondition ? 'orWhereExists' : 'whereExists';

                $scopeQuery->{$method}(function ($delegationQuery) use ($actor, $table) {
                    $delegationQuery->selectRaw('1')
                        ->from('approval_delegations')
                        ->where('approval_delegations.delegate_user_id', (string) $actor->id)
                        ->where('approval_delegations.is_active', true)
                        ->whereIn('approval_delegations.module', [
                            ApprovalDelegation::MODULE_ALL,
                            ApprovalDelegation::MODULE_EMPLOYEE_MOVEMENT,
                        ])
                        ->where(function ($matchQuery) use ($table) {
                            $matchQuery->where(function ($divisionQuery) use ($table) {
                                $divisionQuery->whereNotNull('approval_delegations.divisi_id')
                                    ->whereColumn('approval_delegations.divisi_id', $table . '.divisi_id');
                            })->orWhere(function ($departmentQuery) use ($table) {
                                $departmentQuery->whereNull('approval_delegations.divisi_id')
                                    ->whereNotNull('approval_delegations.departemen_id')
                                    ->whereColumn('approval_delegations.departemen_id', $table . '.departemen_id');
                            });
                        });
                });

                $hasCondition = true;
            }

            if (!$hasCondition) {
                $scopeQuery->whereRaw('1 = 0');
            }
        });
    }

    public function scopeMovementsVisibleTo(Builder $query, User $actor): Builder
    {
        if ($actor->canAccessAllEmployees()) {
            return $query;
        }

        return $query->where(function (Builder $scopeQuery) use ($actor) {
            $hasCondition = false;

            if (filled($actor->id)) {
                $scopeQuery->where('created_by_user_id', (string) $actor->id);
                $hasCondition = true;
            }

            if ($actor->hasRole('HOD')) {
                $method = $hasCondition ? 'orWhereHas' : 'whereHas';

                $scopeQuery->{$method}('employee', function (Builder $employeeQuery) use ($actor) {
                    $actor->applyEmployeeScope($employeeQuery);
                });

                $hasCondition = true;
            }

            if (!$hasCondition) {
                $scopeQuery->whereRaw('1 = 0');
            }
        });
    }

    public function hasActiveMovementDelegation(User $actor): bool
    {
        if (!Schema::hasTable('approval_delegations')) {
            return false;
        }

        return ApprovalDelegation::query()
            ->where('delegate_user_id', (string) $actor->id)
            ->where('is_active', true)
            ->whereIn('module', [
                ApprovalDelegation::MODULE_ALL,
                ApprovalDelegation::MODULE_EMPLOYEE_MOVEMENT,
            ])
            ->exists();
    }

    private function submissionCountsAsHodApproval(Employee $employee, User $actor): bool
    {
        if ($actor->hasRole('Super Admin')) {
            return true;
        }

        return $actor->hasRole('HOD') && $this->scopeAllowsEmployee($employee, $actor);
    }

    private function hasActiveMovementDelegationForEmployee(Employee $employee, User $actor): bool
    {
        if (!Schema::hasTable('approval_delegations')) {
            return false;
        }

        if (blank($employee->departemen_id) && blank($employee->divisi_id)) {
            return false;
        }

        return ApprovalDelegation::query()
            ->where('delegate_user_id', (string) $actor->id)
            ->where('is_active', true)
            ->whereIn('module', [
                ApprovalDelegation::MODULE_ALL,
                ApprovalDelegation::MODULE_EMPLOYEE_MOVEMENT,
            ])
            ->where(function (Builder $query) use ($employee) {
                if (filled($employee->divisi_id)) {
                    $query->orWhere('divisi_id', $employee->divisi_id);
                }

                if (filled($employee->departemen_id)) {
                    $query->orWhere(function (Builder $departmentQuery) use ($employee) {
                        $departmentQuery->whereNull('divisi_id')
                            ->where('departemen_id', $employee->departemen_id);
                    });
                }
            })
            ->exists();
    }

    private function scopeAllowsEmployee(Employee $employee, User $actor): bool
    {
        return $this->scopeAllowsSnapshot($employee->departemen_id, $employee->divisi_id, $actor);
    }

    private function scopeAllowsSnapshot($departemenId, $divisiId, User $actor): bool
    {
        if ($actor->hasRole('Super Admin')) {
            return true;
        }

        if (!$actor->hasRole('HOD')) {
            return false;
        }

        if (filled($divisiId) && in_array((string) $divisiId, $actor->scopedDivisionIds(), true)) {
            return true;
        }

        return filled($departemenId)
            && in_array((string) $departemenId, $actor->scopedDepartmentIds(), true);
    }

    private function snapshot(Employee $employee): array
    {
        return [
            'posisi' => $this->nullableText($employee->posisi),
            'jabatan' => $this->nullableText($employee->jabatan),
            'departemen_id' => $employee->departemen_id ? (int) $employee->departemen_id : null,
            'divisi_id' => $employee->divisi_id ? (int) $employee->divisi_id : null,
        ];
    }

    private function targetValues(string $movementType, array $data, Employee $employee): array
    {
        $old = $this->snapshot($employee);

        if (in_array($movementType, [EmployeeMovement::TYPE_PROMOTION, EmployeeMovement::TYPE_DEMOTION], true)) {
            return [
                'posisi' => $this->nullableText($data['new_posisi'] ?? null),
                'jabatan' => array_key_exists('new_jabatan', $data) && filled($data['new_jabatan'])
                    ? $this->nullableText($data['new_jabatan'])
                    : $old['jabatan'],
                'departemen_id' => $old['departemen_id'],
                'divisi_id' => $old['divisi_id'],
            ];
        }

        $targetDivisiId = filled($data['new_divisi_id'] ?? null) ? (int) $data['new_divisi_id'] : null;
        $targetDepartemenId = filled($data['new_departemen_id'] ?? null) ? (int) $data['new_departemen_id'] : null;

        if ($targetDivisiId) {
            $division = Divisi::query()->select('id', 'departemen_id')->findOrFail($targetDivisiId);
            $targetDepartemenId = $division->departemen_id ? (int) $division->departemen_id : $targetDepartemenId;
        }

        if (!$targetDepartemenId && $targetDivisiId) {
            $targetDepartemenId = $old['departemen_id'];
        }

        return [
            'posisi' => $old['posisi'],
            'jabatan' => $old['jabatan'],
            'departemen_id' => $targetDepartemenId,
            'divisi_id' => $targetDivisiId,
        ];
    }

    private function oldValuesFromMovement(EmployeeMovement $movement): array
    {
        return [
            'posisi' => $this->nullableText($movement->old_posisi),
            'jabatan' => $this->nullableText($movement->old_jabatan),
            'departemen_id' => $movement->old_departemen_id ? (int) $movement->old_departemen_id : null,
            'divisi_id' => $movement->old_divisi_id ? (int) $movement->old_divisi_id : null,
        ];
    }

    private function targetValuesFromMovement(EmployeeMovement $movement): array
    {
        return [
            'posisi' => $this->nullableText($movement->new_posisi),
            'jabatan' => $this->nullableText($movement->new_jabatan),
            'departemen_id' => $movement->new_departemen_id ? (int) $movement->new_departemen_id : null,
            'divisi_id' => $movement->new_divisi_id ? (int) $movement->new_divisi_id : null,
        ];
    }

    private function guardRealChange(string $movementType, array $oldValues, array $targetValues): void
    {
        if (in_array($movementType, [EmployeeMovement::TYPE_PROMOTION, EmployeeMovement::TYPE_DEMOTION], true)) {
            if ($oldValues['posisi'] === $targetValues['posisi'] && $oldValues['jabatan'] === $targetValues['jabatan']) {
                throw ValidationException::withMessages([
                    'new_posisi' => 'Posisi atau jabatan baru harus berbeda dari data karyawan saat ini.',
                ]);
            }

            return;
        }

        if (
            $oldValues['departemen_id'] === $targetValues['departemen_id']
            && $oldValues['divisi_id'] === $targetValues['divisi_id']
        ) {
            throw ValidationException::withMessages([
                'new_departemen_id' => 'Departemen atau divisi tujuan harus berbeda dari penempatan saat ini.',
            ]);
        }
    }

    private function hasChangedSinceSubmission(array $expectedOldValues, array $currentValues): bool
    {
        foreach ($expectedOldValues as $key => $value) {
            if ($currentValues[$key] !== $value) {
                return true;
            }
        }

        return false;
    }

    private function approvalSnapshot(EmployeeMovement $movement): array
    {
        return [
            'status' => $movement->status,
            'hod_status' => (int) $movement->hod_status,
            'hod_processed_by' => $movement->hod_processed_by,
            'hod_processed_at' => optional($movement->hod_processed_at)->toDateTimeString(),
            'hrd_status' => (int) $movement->hrd_status,
            'hrd_processed_by' => $movement->hrd_processed_by,
            'hrd_processed_at' => optional($movement->hrd_processed_at)->toDateTimeString(),
        ];
    }

    private function freshMovement(EmployeeMovement $movement): EmployeeMovement
    {
        return $movement->fresh([
            'employee',
            'oldDepartemen',
            'newDepartemen',
            'oldDivisi',
            'newDivisi',
            'creator',
            'hodProcessor',
            'hrdProcessor',
            'applier',
        ]);
    }

    private function recordMovementAudit(
        string $event,
        EmployeeMovement $movement,
        ?User $actor,
        array $oldValues,
        array $newValues,
        ?string $note = null,
        array $metadata = []
    ): void {
        app(AuditTrailService::class)->record([
            'event' => $event,
            'module' => 'employee_movement',
            'auditable_type' => EmployeeMovement::class,
            'auditable_id' => (string) $movement->id,
            'reference_table' => 'employee_movements',
            'reference_id' => (string) $movement->id,
            'employee_nik' => $movement->employee_nik,
            'actor' => $actor,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
            'note' => $note,
        ]);
    }

    private function nullableText($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
