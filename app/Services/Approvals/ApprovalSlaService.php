<?php

namespace App\Services\Approvals;

use App\Models\ApprovalDelegation;
use App\Models\ApprovalSlaEscalationLog;
use App\Models\AttendanceCorrection;
use App\Models\Cuti;
use App\Models\Roster;
use App\Models\RosterOffRequest;
use App\Models\User;
use App\Notifications\StatusPengajuanNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

class ApprovalSlaService
{
    public const STATUS_ON_TRACK = 'on_track';
    public const STATUS_WARNING = 'warning';
    public const STATUS_BREACHED = 'breached';
    public const STATUS_CRITICAL = 'critical';

    public function modules(): array
    {
        return [
            ApprovalDelegation::MODULE_CUTI => 'Cuti Tahunan',
            ApprovalDelegation::MODULE_IZIN => 'Izin',
            ApprovalDelegation::MODULE_ROSTER => 'Cuti/Insentif Roster',
            ApprovalDelegation::MODULE_ROSTER_OFF => 'OFF Roster',
            ApprovalDelegation::MODULE_ATTENDANCE_CORRECTION => 'Koreksi Presensi',
        ];
    }

    public function stages(): array
    {
        return [
            'delegate' => config('approval_sla.stages.delegate.label', 'Delegasi'),
            'hod' => config('approval_sla.stages.hod.label', 'HOD'),
            'hrd' => config('approval_sla.stages.hrd.label', 'HR'),
        ];
    }

    public function statuses(): array
    {
        return [
            self::STATUS_ON_TRACK => 'Dalam SLA',
            self::STATUS_WARNING => 'Mendekati SLA',
            self::STATUS_BREACHED => 'Lewat SLA',
            self::STATUS_CRITICAL => 'Kritis',
        ];
    }

    public function tableReady(): bool
    {
        return Schema::hasTable('approval_sla_escalation_logs');
    }

    public function normalizeFilters(array $input): array
    {
        $module = $input['module'] ?? 'all';
        $stage = $input['stage'] ?? 'all';
        $status = $input['status'] ?? 'all';

        if ($module !== 'all' && !array_key_exists($module, $this->modules())) {
            $module = 'all';
        }

        if ($stage !== 'all' && !array_key_exists($stage, $this->stages())) {
            $stage = 'all';
        }

        if ($status !== 'all' && !array_key_exists($status, $this->statuses())) {
            $status = 'all';
        }

        return compact('module', 'stage', 'status');
    }

    public function summary(array $filters = []): array
    {
        $items = $this->pendingItems($filters, (int) config('approval_sla.dashboard_limit', 500));

        return $this->summarizeItems($items);
    }

    public function summarizeItems(Collection $items): array
    {
        return [
            'total' => $items->count(),
            self::STATUS_ON_TRACK => $items->where('sla_status', self::STATUS_ON_TRACK)->count(),
            self::STATUS_WARNING => $items->where('sla_status', self::STATUS_WARNING)->count(),
            self::STATUS_BREACHED => $items->where('sla_status', self::STATUS_BREACHED)->count(),
            self::STATUS_CRITICAL => $items->where('sla_status', self::STATUS_CRITICAL)->count(),
            'escalated' => $this->escalationLogCount(),
        ];
    }

    public function pendingItems(array $filters = [], ?int $limit = null): Collection
    {
        $filters = $this->normalizeFilters($filters);
        $items = collect();

        foreach ($this->moduleDefinitions() as $module => $definition) {
            if ($filters['module'] !== 'all' && $filters['module'] !== $module) {
                continue;
            }

            if (!Schema::hasTable($definition['table'])) {
                continue;
            }

            foreach (array_keys($this->stages()) as $stage) {
                if ($filters['stage'] !== 'all' && $filters['stage'] !== $stage) {
                    continue;
                }

                if (!$this->stageSupported($definition, $stage)) {
                    continue;
                }

                $items = $items->merge($this->pendingItemsForStage($module, $definition, $stage));
            }
        }

        $items = $items
            ->filter(function (array $item) use ($filters) {
                return $filters['status'] === 'all' || $item['sla_status'] === $filters['status'];
            })
            ->sortBy(function (array $item) {
                return optional($item['due_at'])->timestamp ?: PHP_INT_MAX;
            })
            ->values();

        if ($limit !== null) {
            $items = $items->take($limit)->values();
        }

        return $items;
    }

    public function escalateOverdue(?User $actor = null, ?int $limit = null, bool $dryRun = false): array
    {
        if (!config('approval_sla.enabled', true)) {
            return [
                'checked' => 0,
                'created' => 0,
                'skipped' => 0,
                'dry_run' => $dryRun,
                'missing_table' => false,
            ];
        }

        if (!$dryRun && !$this->tableReady()) {
            return [
                'checked' => 0,
                'created' => 0,
                'skipped' => 0,
                'dry_run' => $dryRun,
                'missing_table' => true,
            ];
        }

        $limit = $limit ?: (int) config('approval_sla.escalation_limit', 500);
        $items = $this->pendingItems([], $limit)
            ->filter(fn(array $item) => in_array($item['sla_status'], [self::STATUS_BREACHED, self::STATUS_CRITICAL], true))
            ->values();

        $created = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $level = $item['sla_status'] === self::STATUS_CRITICAL ? 2 : 1;

            if ($this->escalationAlreadyLogged($item, $level)) {
                $skipped++;
                continue;
            }

            $recipients = $this->escalationRecipients($level);

            if ($recipients->isEmpty()) {
                $skipped++;
                continue;
            }

            if (!$dryRun) {
                $this->sendEscalationNotification($recipients, $item, $level);
                $this->recordEscalation($item, $level, $actor, $recipients->count());
            }

            $created++;
        }

        return [
            'checked' => $items->count(),
            'created' => $created,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
            'missing_table' => false,
        ];
    }

    public function statusBadgeClass(string $status): string
    {
        switch ($status) {
            case self::STATUS_CRITICAL:
                return 'bg-danger';
            case self::STATUS_BREACHED:
                return 'bg-warning text-dark';
            case self::STATUS_WARNING:
                return 'bg-info text-dark';
            default:
                return 'bg-success';
        }
    }

    private function moduleDefinitions(): array
    {
        return [
            ApprovalDelegation::MODULE_CUTI => [
                'table' => 'cuti_izin',
                'model' => Cuti::class,
                'label' => $this->modules()[ApprovalDelegation::MODULE_CUTI],
                'date_columns' => ['tanggal_mulai', 'tanggal_berakhir'],
                'route' => [
                    'hod' => 'approval.cuti.hod',
                    'hrd' => 'approval.cuti.hrd',
                    'delegate' => 'approval.delegate.index',
                ],
                'constraint' => function ($query) {
                    $query->where('cuti_izin.tipe', 'CUTI');
                },
                'hod_status' => 'status_hod',
                'hrd_status' => 'status_hrd',
            ],
            ApprovalDelegation::MODULE_IZIN => [
                'table' => 'cuti_izin',
                'model' => Cuti::class,
                'label' => $this->modules()[ApprovalDelegation::MODULE_IZIN],
                'date_columns' => ['tanggal_mulai', 'tanggal_berakhir'],
                'route' => [
                    'hod' => 'approval.izin.hod',
                    'hrd' => 'approval.izin.hrd',
                    'delegate' => 'approval.delegate.index',
                ],
                'constraint' => function ($query) {
                    $query->whereIn('cuti_izin.tipe', ['PAID', 'UNPAID']);
                },
                'hod_status' => 'status_hod',
                'hrd_status' => 'status_hrd',
            ],
            ApprovalDelegation::MODULE_ROSTER => [
                'table' => 'cuti_roster',
                'model' => Roster::class,
                'label' => $this->modules()[ApprovalDelegation::MODULE_ROSTER],
                'date_columns' => ['tanggal_pengajuan'],
                'route' => [
                    'hod' => 'approval.roster.hod',
                    'hrd' => 'approval.roster.hrd',
                    'delegate' => 'approval.delegate.index',
                ],
                'constraint' => function ($query) {
                    return $query;
                },
                'hod_status' => 'status_pengajuan',
                'hrd_status' => 'status_pengajuan_hrd',
            ],
            ApprovalDelegation::MODULE_ROSTER_OFF => [
                'table' => 'roster_off_requests',
                'model' => RosterOffRequest::class,
                'label' => $this->modules()[ApprovalDelegation::MODULE_ROSTER_OFF],
                'date_columns' => ['tanggal_off'],
                'route' => [
                    'hod' => 'approval.roster-off.hod',
                    'hrd' => 'approval.roster-off.hrd',
                    'delegate' => 'approval.delegate.index',
                ],
                'constraint' => function ($query) {
                    return $query;
                },
                'hod_status' => 'status_hod',
                'hrd_status' => 'status_hrd',
            ],
            ApprovalDelegation::MODULE_ATTENDANCE_CORRECTION => [
                'table' => 'attendance_corrections',
                'model' => AttendanceCorrection::class,
                'label' => $this->modules()[ApprovalDelegation::MODULE_ATTENDANCE_CORRECTION],
                'date_columns' => ['tanggal'],
                'route' => [
                    'hod' => 'approval.attendance-corrections.hod',
                    'hrd' => 'approval.attendance-corrections.hrd',
                    'delegate' => 'approval.delegate.index',
                ],
                'constraint' => function ($query) {
                    return $query;
                },
                'hod_status' => 'status_hod',
                'hrd_status' => 'status_hrd',
            ],
        ];
    }

    private function pendingItemsForStage(string $module, array $definition, string $stage): Collection
    {
        $table = $definition['table'];
        $query = DB::table($table)
            ->join('employees', 'employees.nik', '=', $table . '.nik_karyawan')
            ->leftJoin('departemens', 'departemens.id', '=', 'employees.departemen_id')
            ->leftJoin('divisis', 'divisis.id', '=', 'employees.divisi_id')
            ->where('employees.status_resign', 'AKTIF')
            ->whereIn('employees.area_kerja', ['VDNI', 'VDNIP']);

        ($definition['constraint'])($query);
        $this->applyStagePendingFilter($query, $definition, $stage);

        return $query
            ->select($this->selectColumns($definition, $stage))
            ->orderByRaw($this->referenceExpression($definition, $stage))
            ->limit((int) config('approval_sla.dashboard_limit', 500))
            ->get()
            ->map(function ($row) use ($module, $definition, $stage) {
                return $this->formatItem($row, $module, $definition, $stage);
            });
    }

    private function applyStagePendingFilter($query, array $definition, string $stage): void
    {
        $table = $definition['table'];

        if ($stage === 'delegate') {
            $query->where($table . '.delegate_status', ApprovalDelegationService::STATUS_PENDING);
            return;
        }

        if ($stage === 'hod') {
            $query->where($table . '.' . $definition['hod_status'], ApprovalDelegationService::STATUS_PENDING);

            if ($this->hasDelegateColumns($table)) {
                $query->where(function ($query) use ($table) {
                    $query->whereNull($table . '.delegate_status')
                        ->orWhere($table . '.delegate_status', ApprovalDelegationService::STATUS_APPROVED);
                });
            }

            return;
        }

        $query->where($table . '.' . $definition['hod_status'], ApprovalDelegationService::STATUS_APPROVED)
            ->where($table . '.' . $definition['hrd_status'], ApprovalDelegationService::STATUS_PENDING);
    }

    private function selectColumns(array $definition, string $stage): array
    {
        $table = $definition['table'];
        $columns = [
            $table . '.id as approvable_id',
            $table . '.nik_karyawan',
            $table . '.created_at',
            $table . '.updated_at',
            'employees.nama_karyawan',
            'employees.area_kerja',
            'departemens.departemen as departemen_name',
            'divisis.nama_divisi as divisi_name',
        ];

        foreach ($definition['date_columns'] as $column) {
            $columns[] = Schema::hasColumn($table, $column)
                ? $table . '.' . $column . ' as ' . $column
                : DB::raw('NULL as ' . $column);
        }

        foreach (['delegate_processed_at', 'hod_processed_at'] as $column) {
            $columns[] = Schema::hasColumn($table, $column)
                ? $table . '.' . $column . ' as ' . $column
                : DB::raw('NULL as ' . $column);
        }

        $columns[] = DB::raw($this->referenceExpression($definition, $stage) . ' as sla_started_at');

        return $columns;
    }

    private function referenceExpression(array $definition, string $stage): string
    {
        $table = $definition['table'];

        if ($stage === 'delegate') {
            return $table . '.created_at';
        }

        if ($stage === 'hod') {
            return $this->hasDelegateColumns($table)
                ? 'COALESCE(' . $table . '.delegate_processed_at, ' . $table . '.created_at)'
                : $table . '.created_at';
        }

        if (Schema::hasColumn($table, 'hod_processed_at')) {
            return 'COALESCE(' . $table . '.hod_processed_at, ' . $table . '.updated_at, ' . $table . '.created_at)';
        }

        return 'COALESCE(' . $table . '.updated_at, ' . $table . '.created_at)';
    }

    private function formatItem($row, string $module, array $definition, string $stage): array
    {
        $startedAt = $row->sla_started_at
            ? Carbon::parse($row->sla_started_at)
            : Carbon::parse($row->created_at);
        $slaHours = $this->slaHours($stage);
        $dueAt = $startedAt->copy()->addHours($slaHours);
        $ageMinutes = (int) $startedAt->diffInMinutes(now(), true);
        $status = $this->resolveStatus($ageMinutes, $slaHours);

        return [
            'module' => $module,
            'module_label' => $definition['label'],
            'stage' => $stage,
            'stage_label' => $this->stages()[$stage] ?? strtoupper($stage),
            'approvable_type' => $definition['model'],
            'approvable_id' => (string) $row->approvable_id,
            'nik_karyawan' => $row->nik_karyawan,
            'employee_name' => $row->nama_karyawan ?: $row->nik_karyawan,
            'area_kerja' => $row->area_kerja,
            'departemen' => $row->departemen_name ?: '-',
            'divisi' => $row->divisi_name ?: '-',
            'request_period' => $this->requestPeriod($row, $definition['date_columns']),
            'sla_started_at' => $startedAt,
            'due_at' => $dueAt,
            'sla_hours' => $slaHours,
            'age_hours' => round($ageMinutes / 60, 1),
            'remaining_hours' => $this->remainingHours($dueAt),
            'sla_status' => $status,
            'sla_status_label' => $this->statuses()[$status],
            'sla_status_badge' => $this->statusBadgeClass($status),
            'approval_url' => $this->approvalUrl($definition, $module, $stage),
        ];
    }

    private function remainingHours(Carbon $dueAt): float
    {
        if (now()->greaterThanOrEqualTo($dueAt)) {
            return 0.0;
        }

        return round((int) now()->diffInMinutes($dueAt, true) / 60, 1);
    }

    private function resolveStatus(int $ageMinutes, int $slaHours): string
    {
        $slaMinutes = max(1, $slaHours * 60);
        $warningMinutes = (int) floor($slaMinutes * ((int) config('approval_sla.warning_percent', 80) / 100));
        $criticalMinutes = (int) floor($slaMinutes * (float) config('approval_sla.critical_multiplier', 2));

        if ($ageMinutes >= $criticalMinutes) {
            return self::STATUS_CRITICAL;
        }

        if ($ageMinutes >= $slaMinutes) {
            return self::STATUS_BREACHED;
        }

        if ($ageMinutes >= $warningMinutes) {
            return self::STATUS_WARNING;
        }

        return self::STATUS_ON_TRACK;
    }

    private function requestPeriod($row, array $dateColumns): string
    {
        $dates = collect($dateColumns)
            ->map(fn(string $column) => $row->{$column} ?? null)
            ->filter()
            ->map(fn($date) => $this->formatDateSafely($date))
            ->filter()
            ->values();

        if ($dates->isEmpty()) {
            return '-';
        }

        return $dates->unique()->count() === 1
            ? $dates->first()
            : $dates->first() . ' - ' . $dates->last();
    }

    private function formatDateSafely($date): ?string
    {
        try {
            return Carbon::parse($date)->format('d M Y');
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function approvalUrl(array $definition, string $module, string $stage): string
    {
        if ($stage === 'delegate') {
            return route($definition['route'][$stage], ['module' => str_replace('_', '-', $module)]);
        }

        return route($definition['route'][$stage]);
    }

    private function slaHours(string $stage): int
    {
        return max(1, (int) config('approval_sla.stages.' . $stage . '.hours', 24));
    }

    private function stageSupported(array $definition, string $stage): bool
    {
        if ($stage === 'delegate') {
            return $this->hasDelegateColumns($definition['table']);
        }

        return Schema::hasColumn($definition['table'], $definition[$stage === 'hod' ? 'hod_status' : 'hrd_status']);
    }

    private function hasDelegateColumns(string $table): bool
    {
        return Schema::hasColumn($table, 'delegate_status')
            && Schema::hasColumn($table, 'delegate_processed_at');
    }

    private function escalationLogCount(): int
    {
        return Schema::hasTable('approval_sla_escalation_logs')
            ? ApprovalSlaEscalationLog::query()->count()
            : 0;
    }

    private function escalationAlreadyLogged(array $item, int $level): bool
    {
        return Schema::hasTable('approval_sla_escalation_logs')
            && ApprovalSlaEscalationLog::query()
                ->where('approvable_type', $item['approvable_type'])
                ->where('approvable_id', $item['approvable_id'])
                ->where('stage', $item['stage'])
                ->where('escalation_level', $level)
                ->exists();
    }

    private function escalationRecipients(int $level): Collection
    {
        $roles = $level >= 2 ? ['Super Admin', 'HR'] : ['HR'];

        return $this->activeUsersForRoles($roles)->get();
    }

    private function activeUsersForRoles(array $roles): Builder
    {
        if (!Schema::hasTable('roles')) {
            return User::query()->whereRaw('1 = 0');
        }

        $roleNames = collect($roles)
            ->flatMap(function ($role) {
                return array_merge([$role], config('access.roles.' . $role . '.aliases', []));
            })
            ->unique()
            ->values()
            ->all();

        return User::query()
            ->with(array_filter(['role', Schema::hasTable('role_user') ? 'additionalRoles' : null]))
            ->where(function (Builder $query) {
                $query->whereNull('status')
                    ->orWhere('status', 'aktif');
            })
            ->where(function (Builder $query) use ($roleNames) {
                $query->whereHas('role', function (Builder $roleQuery) use ($roleNames) {
                    $roleQuery->whereIn('permission_role', $roleNames);
                });

                if (Schema::hasTable('role_user')) {
                    $query->orWhereHas('additionalRoles', function (Builder $roleQuery) use ($roleNames) {
                        $roleQuery->whereIn('permission_role', $roleNames);
                    });
                }
            });
    }

    private function sendEscalationNotification(Collection $recipients, array $item, int $level): void
    {
        $payload = [
            'judul' => 'SLA Approval Level ' . $level,
            'pesan' => $item['module_label'] . ' ' . $item['employee_name']
                . ' pada tahap ' . $item['stage_label']
                . ' sudah melewati SLA ' . $item['sla_hours'] . ' jam.',
            'url' => route('approval-sla.index', [
                'module' => $item['module'],
                'stage' => $item['stage'],
                'status' => $item['sla_status'],
            ]),
            'tipe' => 'SLA Approval',
        ];

        Notification::send($recipients, new StatusPengajuanNotification($payload));
    }

    private function recordEscalation(array $item, int $level, ?User $actor, int $recipientCount): void
    {
        if (!Schema::hasTable('approval_sla_escalation_logs')) {
            return;
        }

        ApprovalSlaEscalationLog::query()->create([
            'module' => $item['module'],
            'stage' => $item['stage'],
            'approvable_type' => $item['approvable_type'],
            'approvable_id' => $item['approvable_id'],
            'escalation_level' => $level,
            'sla_started_at' => $item['sla_started_at'],
            'due_at' => $item['due_at'],
            'escalated_at' => now(),
            'escalated_by' => $actor ? (string) $actor->id : null,
            'recipient_count' => $recipientCount,
            'message' => $item['module_label'] . ' ' . $item['employee_name'] . ' tahap ' . $item['stage_label'],
            'metadata' => [
                'nik_karyawan' => $item['nik_karyawan'],
                'employee_name' => $item['employee_name'],
                'sla_status' => $item['sla_status'],
                'age_hours' => $item['age_hours'],
            ],
        ]);
    }
}
