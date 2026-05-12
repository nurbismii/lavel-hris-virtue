<?php

namespace App\Services\Approvals;

use App\Models\ApprovalDelegation;
use App\Models\ApprovalDelegationAssignment;
use App\Models\AttendanceCorrection;
use App\Models\Cuti;
use App\Models\Divisi;
use App\Models\Employee;
use App\Models\Roster;
use App\Models\RosterOffRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ApprovalDelegationService
{
    public const STATUS_PENDING = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;

    public function availableModules(): array
    {
        return ApprovalDelegation::moduleLabels();
    }

    public function approvableModules(): array
    {
        return collect($this->availableModules())
            ->except(ApprovalDelegation::MODULE_ALL)
            ->all();
    }

    public function normalizeModule(?string $module): ?string
    {
        if (!$module) {
            return null;
        }

        $module = str_replace('-', '_', trim($module));

        return array_key_exists($module, $this->availableModules()) ? $module : null;
    }

    public function moduleLabel(string $module): string
    {
        return $this->availableModules()[$module] ?? $module;
    }

    public function targetForModule(string $module): ?array
    {
        switch ($module) {
            case ApprovalDelegation::MODULE_CUTI:
                return [
                    'model' => Cuti::class,
                    'table' => 'cuti_izin',
                    'with' => ['employee.divisi.departemen'],
                    'route' => 'cuti.index',
                    'constraint' => function (Builder $query): Builder {
                        return $query->where('tipe', 'CUTI');
                    },
                ];

            case ApprovalDelegation::MODULE_IZIN:
                return [
                    'model' => Cuti::class,
                    'table' => 'cuti_izin',
                    'with' => ['employee.divisi.departemen'],
                    'route' => 'izin.index',
                    'constraint' => function (Builder $query): Builder {
                        return $query->whereIn('tipe', ['PAID', 'UNPAID']);
                    },
                ];

            case ApprovalDelegation::MODULE_ROSTER:
                return [
                    'model' => Roster::class,
                    'table' => 'cuti_roster',
                    'with' => ['employee.divisi.departemen', 'periodeKerjaRoster'],
                    'route' => 'roster.index',
                    'constraint' => function (Builder $query): Builder {
                        return $query;
                    },
                ];

            case ApprovalDelegation::MODULE_ROSTER_OFF:
                return [
                    'model' => RosterOffRequest::class,
                    'table' => 'roster_off_requests',
                    'with' => ['employee.divisi.departemen'],
                    'route' => 'roster-off.index',
                    'constraint' => function (Builder $query): Builder {
                        return $query;
                    },
                ];

            case ApprovalDelegation::MODULE_ATTENDANCE_CORRECTION:
                return [
                    'model' => AttendanceCorrection::class,
                    'table' => 'attendance_corrections',
                    'with' => ['employee.divisi.departemen', 'requester:id,name,nik_karyawan'],
                    'route' => 'attendance-corrections.index',
                    'constraint' => function (Builder $query): Builder {
                        return $query;
                    },
                ];
        }

        return null;
    }

    public function queryForModule(string $module): Builder
    {
        $target = $this->targetForModule($module);
        $modelClass = $target['model'];
        $query = $modelClass::query()->with($target['with']);

        return $target['constraint']($query);
    }

    public function activeDelegationsForEmployee(Employee $employee, string $module, ?User $requester = null): Collection
    {
        if (!Schema::hasTable('approval_delegations')) {
            return collect();
        }

        if (blank($employee->departemen_id) && blank($employee->divisi_id)) {
            return collect();
        }

        return ApprovalDelegation::query()
            ->with(['delegate.employee'])
            ->where('is_active', true)
            ->whereIn('module', [$module, ApprovalDelegation::MODULE_ALL])
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
            ->when($requester && filled($requester->id), function (Builder $query) use ($requester) {
                $query->where('delegate_user_id', '!=', (string) $requester->id);
            })
            ->get()
            ->filter(function (ApprovalDelegation $delegation) use ($requester) {
                if (!$delegation->delegate) {
                    return false;
                }

                if ($requester && (string) $delegation->delegate->nik_karyawan === (string) $requester->nik_karyawan) {
                    return false;
                }

                return true;
            })
            ->unique('delegate_user_id')
            ->values();
    }

    public function submissionPayload(string $table, Collection $delegations): array
    {
        if (!$this->hasDelegateColumns($table) || $delegations->isEmpty()) {
            return [];
        }

        return [
            'delegate_status' => self::STATUS_PENDING,
        ];
    }

    public function createAssignments(Model $model, Collection $delegations, string $module): void
    {
        if ($delegations->isEmpty() || !Schema::hasTable('approval_delegation_request_assignments')) {
            return;
        }

        $now = now();
        $rows = $delegations
            ->unique('delegate_user_id')
            ->map(function (ApprovalDelegation $delegation) use ($model, $module, $now) {
                return [
                    'approval_delegation_id' => $delegation->id,
                    'approvable_type' => get_class($model),
                    'approvable_id' => (string) $model->getKey(),
                    'delegate_user_id' => (string) $delegation->delegate_user_id,
                    'assigned_by_hod_user_id' => (string) $delegation->hod_user_id,
                    'module' => $module,
                    'status' => self::STATUS_PENDING,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->values()
            ->all();

        if (!empty($rows)) {
            ApprovalDelegationAssignment::query()->insertOrIgnore($rows);
        }
    }

    public function restrictReadyForHod(Builder $query, string $table): Builder
    {
        if (!$this->hasDelegateColumns($table)) {
            return $query;
        }

        return $query->where(function (Builder $approvalQuery) use ($table) {
            $approvalQuery
                ->whereNull($table . '.delegate_status')
                ->orWhere($table . '.delegate_status', self::STATUS_APPROVED);
        });
    }

    public function blocksHodApproval(Model $model, string $table): bool
    {
        if (!$this->hasDelegateColumns($table)) {
            return false;
        }

        $status = $model->getAttribute('delegate_status');

        if ($status === null) {
            return false;
        }

        return (int) $status === self::STATUS_PENDING
            || (int) $status === self::STATUS_REJECTED;
    }

    public function restrictPendingForDelegate(
        Builder $query,
        User $user,
        string $module,
        string $table,
        string $modelClass
    ): Builder {
        if (!$this->hasDelegateColumns($table) || !Schema::hasTable('approval_delegation_request_assignments')) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where($table . '.delegate_status', self::STATUS_PENDING)
            ->when(filled($user->nik_karyawan), function (Builder $scopeQuery) use ($table, $user) {
                $scopeQuery->where($table . '.nik_karyawan', '!=', (string) $user->nik_karyawan);
            })
            ->whereExists(function ($assignmentQuery) use ($user, $module, $table, $modelClass) {
                $assignmentQuery->selectRaw('1')
                    ->from('approval_delegation_request_assignments')
                    ->whereColumn('approval_delegation_request_assignments.approvable_id', $table . '.id')
                    ->where('approval_delegation_request_assignments.approvable_type', $modelClass)
                    ->where('approval_delegation_request_assignments.delegate_user_id', (string) $user->id)
                    ->where('approval_delegation_request_assignments.module', $module);
            });
    }

    public function processDelegateApproval(
        Model $model,
        User $actor,
        string $module,
        string $table,
        int $action,
        ?string $note = null
    ): array {
        if (!$this->canProcessDelegatedModel($model, $actor, $module, $table)) {
            return [
                'status' => false,
                'message' => 'Pengajuan ini tidak tersedia dalam antrean delegasi Anda.',
            ];
        }

        if ((int) $model->getAttribute('delegate_status') !== self::STATUS_PENDING) {
            return [
                'status' => false,
                'message' => 'Pengajuan sudah diproses oleh delegasi.',
            ];
        }

        $auditService = app(ApprovalAuditService::class);
        $oldValues = $auditService->approvalValues($table, $model);

        $model->update(array_merge([
            'delegate_status' => $action,
        ], $auditService->payload(
            $table,
            'delegate',
            $action,
            $actor,
            $note
        )));

        ApprovalDelegationAssignment::query()
            ->where('approvable_type', get_class($model))
            ->where('approvable_id', (string) $model->getKey())
            ->where('delegate_user_id', (string) $actor->id)
            ->where('module', $module)
            ->update([
                'status' => $action,
                'processed_by' => (string) $actor->id,
                'processed_at' => now(),
                'updated_at' => now(),
            ]);

        $freshModel = $model->fresh();

        $auditService->record(
            $table,
            $freshModel,
            'delegate',
            $action,
            $actor,
            $note,
            $oldValues
        );

        return [
            'status' => true,
            'model' => $freshModel,
            'approval_status' => $action === self::STATUS_APPROVED ? 'Disetujui' : 'Ditolak',
        ];
    }

    public function canProcessDelegatedModel(Model $model, User $user, string $module, string $table): bool
    {
        if (!$this->hasDelegateColumns($table) || !Schema::hasTable('approval_delegation_request_assignments')) {
            return false;
        }

        if (filled($user->nik_karyawan) && (string) $model->getAttribute('nik_karyawan') === (string) $user->nik_karyawan) {
            return false;
        }

        return ApprovalDelegationAssignment::query()
            ->where('approvable_type', get_class($model))
            ->where('approvable_id', (string) $model->getKey())
            ->where('delegate_user_id', (string) $user->id)
            ->where('module', $module)
            ->exists();
    }

    public function countsForDelegate(User $user): array
    {
        $counts = [
            'cuti' => 0,
            'izin' => 0,
            'roster' => 0,
            'roster_off' => 0,
            'attendance_correction' => 0,
            'total' => 0,
        ];

        foreach (array_keys($this->approvableModules()) as $module) {
            $target = $this->targetForModule($module);

            if (!$target || !Schema::hasTable($target['table'])) {
                continue;
            }

            $counts[$module] = $this->restrictPendingForDelegate(
                $this->queryForModule($module),
                $user,
                $module,
                $target['table'],
                $target['model']
            )->count();
        }

        $counts['total'] = collect($counts)->except('total')->sum();

        return $counts;
    }

    public function hasDelegateAccess(User $user): bool
    {
        if (!Schema::hasTable('approval_delegations')) {
            return false;
        }

        return ApprovalDelegation::query()
            ->where('delegate_user_id', (string) $user->id)
            ->where('is_active', true)
            ->exists();
    }

    public function canManageScope(User $user, ?string $departemenId, ?string $divisiId): bool
    {
        if ($user->hasRole('Super Admin')) {
            return filled($departemenId) || filled($divisiId);
        }

        if (!$user->hasRole('HOD')) {
            return false;
        }

        if (filled($divisiId)) {
            return in_array((string) $divisiId, $user->scopedDivisionIds(), true);
        }

        if (filled($departemenId)) {
            return in_array((string) $departemenId, $user->scopedDepartmentIds(), true);
        }

        return false;
    }

    public function scopeMatchesEmployee(Employee $employee, ?string $departemenId, ?string $divisiId): bool
    {
        if (filled($divisiId)) {
            return (string) $employee->divisi_id === (string) $divisiId;
        }

        if (filled($departemenId)) {
            return (string) $employee->departemen_id === (string) $departemenId;
        }

        return false;
    }

    public function scopedDepartmentIdsFor(User $user): array
    {
        return $user->hasRole('Super Admin') ? [] : $user->scopedDepartmentIds();
    }

    public function divisionDepartmentId(?string $divisiId): ?string
    {
        if (blank($divisiId)) {
            return null;
        }

        return optional(Divisi::query()->find($divisiId))->departemen_id;
    }

    private function hasDelegateColumns(string $table): bool
    {
        return Schema::hasTable($table)
            && Schema::hasColumn($table, 'delegate_status')
            && Schema::hasColumn($table, 'delegate_processed_by')
            && Schema::hasColumn($table, 'delegate_processed_at')
            && Schema::hasColumn($table, 'delegate_rejection_reason');
    }
}
