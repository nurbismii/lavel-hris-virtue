<?php

namespace App\Services\Approvals;

use App\Models\AttendanceCorrection;
use App\Models\Cuti;
use App\Models\Roster;
use App\Models\RosterOffRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class HodApprovalDashboardService
{
    private const STATUS_PENDING = 0;
    private const STATUS_APPROVED = 1;

    private ApprovalDelegationService $delegationService;

    public function __construct(ApprovalDelegationService $delegationService)
    {
        $this->delegationService = $delegationService;
    }

    public function dashboard(User $user): array
    {
        $modules = collect($this->moduleDefinitions())
            ->filter(fn(array $definition) => Schema::hasTable($definition['table']));

        $moduleCards = collect();
        $priorityItems = collect();
        $hrdWaitingItems = collect();
        $organizationRows = collect();

        foreach ($modules as $key => $definition) {
            $hodPendingQuery = $this->stageQuery($user, $definition, 'hod');
            $hrdPendingQuery = $this->stageQuery($user, $definition, 'hrd');
            $delegationPendingQuery = $this->delegationPendingQuery($user, $definition);

            $pendingHod = (clone $hodPendingQuery)->count();
            $pendingHrd = (clone $hrdPendingQuery)->count();
            $delegationPending = $delegationPendingQuery ? (clone $delegationPendingQuery)->count() : 0;
            $overSla = $this->overSlaCount(clone $hodPendingQuery, $definition, 'hod');
            $dueSoon = $this->dueSoonCount(clone $hodPendingQuery, $definition);
            $effectiveOverdue = $this->effectiveOverdueCount(clone $hodPendingQuery, $definition);

            $moduleCards->push(array_merge($definition, [
                'key' => $key,
                'pending_hod' => $pendingHod,
                'pending_hrd' => $pendingHrd,
                'delegation_pending' => $delegationPending,
                'over_sla' => $overSla,
                'due_soon' => $dueSoon,
                'effective_overdue' => $effectiveOverdue,
            ]));

            $priorityItems = $priorityItems->merge(
                $this->stageItems(clone $hodPendingQuery, $definition, 'hod', 6)
            );
            $hrdWaitingItems = $hrdWaitingItems->merge(
                $this->stageItems(clone $hrdPendingQuery, $definition, 'hrd', 4)
            );
            $organizationRows = $organizationRows->merge(
                $this->organizationRows(clone $hodPendingQuery, $definition, 'HOD')
            )->merge(
                $this->organizationRows(clone $hrdPendingQuery, $definition, 'HRD')
            );
        }

        $summary = [
            'pending_hod' => (int) $moduleCards->sum('pending_hod'),
            'pending_hrd' => (int) $moduleCards->sum('pending_hrd'),
            'delegation_pending' => (int) $moduleCards->sum('delegation_pending'),
            'over_sla' => (int) $moduleCards->sum('over_sla'),
            'due_soon' => (int) $moduleCards->sum('due_soon'),
            'effective_overdue' => (int) $moduleCards->sum('effective_overdue'),
            'total_visible' => (int) $moduleCards->sum(fn(array $module) => $module['pending_hod'] + $module['pending_hrd']),
        ];

        return [
            'summary' => $summary,
            'modules' => $moduleCards->values(),
            'priority_items' => $this->sortItems($priorityItems)->take(12)->values(),
            'hrd_waiting_items' => $this->sortItems($hrdWaitingItems)->take(8)->values(),
            'organization_breakdown' => $this->organizationBreakdown($organizationRows),
            'insights' => $this->insights($summary, $moduleCards),
            'generated_at' => now()->format('d M Y H:i'),
            'sla_hours' => [
                'hod' => (int) config('approval_sla.stages.hod.hours', 24),
                'hrd' => (int) config('approval_sla.stages.hrd.hours', 24),
            ],
        ];
    }

    private function moduleDefinitions(): array
    {
        return [
            'cuti' => [
                'label' => 'Cuti Tahunan',
                'short_label' => 'Cuti',
                'table' => 'cuti_izin',
                'model' => Cuti::class,
                'with' => ['employee.departemen', 'employee.divisi'],
                'constraint' => fn(Builder $query) => $query->where('cuti_izin.tipe', 'CUTI'),
                'hod_status_column' => 'status_hod',
                'hrd_status_column' => 'status_hrd',
                'submitted_column' => 'tanggal',
                'effective_columns' => ['tanggal_mulai', 'tanggal_berakhir'],
                'amount_column' => 'jumlah',
                'route_hod' => 'approval.cuti.hod',
                'route_hrd' => 'approval.cuti.hrd',
                'icon' => 'fas fa-calendar-check',
                'badge' => 'success',
            ],
            'izin' => [
                'label' => 'Izin Paid/Unpaid',
                'short_label' => 'Izin',
                'table' => 'cuti_izin',
                'model' => Cuti::class,
                'with' => ['employee.departemen', 'employee.divisi'],
                'constraint' => fn(Builder $query) => $query->whereIn('cuti_izin.tipe', ['PAID', 'UNPAID']),
                'hod_status_column' => 'status_hod',
                'hrd_status_column' => 'status_hrd',
                'submitted_column' => 'tanggal',
                'effective_columns' => ['tanggal_mulai', 'tanggal_berakhir'],
                'amount_column' => 'jumlah',
                'route_hod' => 'approval.izin.hod',
                'route_hrd' => 'approval.izin.hrd',
                'icon' => 'fas fa-file-signature',
                'badge' => 'primary',
            ],
            'roster' => [
                'label' => 'Cuti/Insentif Roster',
                'short_label' => 'Roster',
                'table' => 'cuti_roster',
                'model' => Roster::class,
                'with' => ['employee.departemen', 'employee.divisi', 'periodeKerjaRoster'],
                'constraint' => fn(Builder $query) => $query,
                'hod_status_column' => 'status_pengajuan',
                'hrd_status_column' => 'status_pengajuan_hrd',
                'submitted_column' => 'tanggal_pengajuan',
                'effective_columns' => [
                    'tgl_mulai_cuti',
                    'tgl_mulai_cuti_tahunan',
                    'tgl_mulai_off',
                    'tgl_awal_kerja',
                    'tanggal_pengajuan',
                ],
                'amount_column' => null,
                'route_hod' => 'approval.roster.hod',
                'route_hrd' => 'approval.roster.hrd',
                'icon' => 'fas fa-plane-departure',
                'badge' => 'warning',
            ],
            'roster_off' => [
                'label' => 'OFF Roster',
                'short_label' => 'OFF Roster',
                'table' => 'roster_off_requests',
                'model' => RosterOffRequest::class,
                'with' => ['employee.departemen', 'employee.divisi'],
                'constraint' => fn(Builder $query) => $query,
                'hod_status_column' => 'status_hod',
                'hrd_status_column' => 'status_hrd',
                'submitted_column' => 'created_at',
                'effective_columns' => ['tanggal_off'],
                'amount_column' => null,
                'route_hod' => 'approval.roster-off.hod',
                'route_hrd' => 'approval.roster-off.hrd',
                'icon' => 'fas fa-times-circle',
                'badge' => 'info',
            ],
            'attendance_correction' => [
                'label' => 'Koreksi Presensi',
                'short_label' => 'Koreksi',
                'table' => 'attendance_corrections',
                'model' => AttendanceCorrection::class,
                'with' => ['employee.departemen', 'employee.divisi'],
                'constraint' => fn(Builder $query) => $query,
                'hod_status_column' => 'status_hod',
                'hrd_status_column' => 'status_hrd',
                'submitted_column' => 'created_at',
                'effective_columns' => ['tanggal'],
                'amount_column' => null,
                'route_hod' => 'approval.attendance-corrections.hod',
                'route_hrd' => 'approval.attendance-corrections.hrd',
                'icon' => 'fas fa-user-clock',
                'badge' => 'danger',
            ],
        ];
    }

    private function stageQuery(User $user, array $definition, string $stage): Builder
    {
        $table = $definition['table'];
        $query = $this->baseScopedQuery($user, $definition);

        if ($stage === 'hod') {
            $query = $this->delegationService->restrictReadyForHod($query, $table)
                ->where($table . '.' . $definition['hod_status_column'], self::STATUS_PENDING);
        } else {
            $query->where($table . '.' . $definition['hod_status_column'], self::STATUS_APPROVED)
                ->where($table . '.' . $definition['hrd_status_column'], self::STATUS_PENDING);
        }

        return $query;
    }

    private function delegationPendingQuery(User $user, array $definition): ?Builder
    {
        $table = $definition['table'];

        if (!$this->hasDelegateColumns($table)) {
            return null;
        }

        return $this->baseScopedQuery($user, $definition)
            ->where($table . '.' . $definition['hod_status_column'], self::STATUS_PENDING)
            ->where($table . '.delegate_status', self::STATUS_PENDING);
    }

    private function baseScopedQuery(User $user, array $definition): Builder
    {
        $table = $definition['table'];
        $model = $definition['model'];

        /** @var Builder $query */
        $query = $model::query()
            ->select($table . '.*')
            ->join('employees', $table . '.nik_karyawan', '=', 'employees.nik')
            ->with($definition['with']);

        ($definition['constraint'])($query);

        return $user->applyEmployeeScope($query, 'employees');
    }

    private function overSlaCount(Builder $query, array $definition, string $stage): int
    {
        $table = $definition['table'];

        if (!Schema::hasColumn($table, 'created_at')) {
            return 0;
        }

        $hours = max(1, (int) config('approval_sla.stages.' . $stage . '.hours', 24));

        return (int) $query
            ->where($table . '.created_at', '<=', now()->subHours($hours))
            ->count();
    }

    private function dueSoonCount(Builder $query, array $definition): int
    {
        $columns = $this->availableEffectiveColumns($definition);

        if (empty($columns)) {
            return 0;
        }

        return (int) $query
            ->where(function (Builder $dateQuery) use ($definition, $columns) {
                foreach ($columns as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $dateQuery->{$method}(function (Builder $columnQuery) use ($definition, $column) {
                        $columnQuery
                            ->whereDate($definition['table'] . '.' . $column, '>=', Carbon::today())
                            ->whereDate($definition['table'] . '.' . $column, '<=', Carbon::today()->addDays(7));
                    });
                }
            })
            ->count();
    }

    private function effectiveOverdueCount(Builder $query, array $definition): int
    {
        $columns = $this->availableEffectiveColumns($definition);

        if (empty($columns)) {
            return 0;
        }

        return (int) $query
            ->where(function (Builder $dateQuery) use ($definition, $columns) {
                foreach ($columns as $index => $column) {
                    $method = $index === 0 ? 'whereDate' : 'orWhereDate';
                    $dateQuery->{$method}($definition['table'] . '.' . $column, '<', Carbon::today());
                }
            })
            ->count();
    }

    private function stageItems(Builder $query, array $definition, string $stage, int $limit): Collection
    {
        $table = $definition['table'];
        $orderColumn = Schema::hasColumn($table, 'created_at')
            ? $table . '.created_at'
            : $table . '.' . ($this->primaryEffectiveColumn($definition) ?: 'id');

        return $query
            ->orderBy($orderColumn)
            ->orderBy($table . '.id')
            ->limit($limit)
            ->get()
            ->map(fn(Model $model) => $this->normalizeItem($model, $definition, $stage));
    }

    private function normalizeItem(Model $model, array $definition, string $stage): array
    {
        $employee = $model->employee;
        $submittedAt = $this->modelDate($model, $definition['submitted_column']);
        $effectiveStart = $this->firstModelDate($model, $definition['effective_columns']);
        $effectiveEnd = $this->lastModelDate($model, $definition['effective_columns']);
        $createdAt = $this->modelDate($model, 'created_at') ?: $submittedAt;

        return [
            'module' => $definition['label'],
            'short_module' => $definition['short_label'],
            'badge' => $definition['badge'],
            'stage' => $stage,
            'stage_label' => $stage === 'hod' ? 'Menunggu HOD' : 'Menunggu HRD',
            'employee_name' => optional($employee)->nama_karyawan ?: '-',
            'nik' => $model->nik_karyawan,
            'department' => optional(optional($employee)->departemen)->departemen ?: '-',
            'division' => optional(optional($employee)->divisi)->nama_divisi ?: '-',
            'submitted_at' => $submittedAt,
            'effective_start' => $effectiveStart,
            'effective_end' => $effectiveEnd,
            'amount' => $definition['amount_column'] ? $model->{$definition['amount_column']} : null,
            'age_hours' => $createdAt ? $createdAt->diffInHours(now()) : null,
            'age_days' => $createdAt ? $createdAt->diffInDays(now()) : null,
            'route' => route($stage === 'hod' ? $definition['route_hod'] : $definition['route_hrd']),
        ];
    }

    private function organizationRows(Builder $query, array $definition, string $stageLabel): Collection
    {
        if (!Schema::hasTable('departemens') || !Schema::hasTable('divisis')) {
            return collect();
        }

        $query->getQuery()->columns = null;
        $query->setEagerLoads([]);

        return $query
            ->reorder()
            ->leftJoin('departemens', 'departemens.id', '=', 'employees.departemen_id')
            ->leftJoin('divisis', 'divisis.id', '=', 'employees.divisi_id')
            ->selectRaw('COALESCE(departemens.departemen, ?) as department_name', ['Tanpa departemen'])
            ->selectRaw('COALESCE(divisis.nama_divisi, ?) as division_name', ['Tanpa divisi'])
            ->selectRaw('? as stage_label', [$stageLabel])
            ->selectRaw('COUNT(*) as total')
            ->groupBy('departemens.departemen', 'divisis.nama_divisi')
            ->get()
            ->map(fn($row) => [
                'department' => $row->department_name,
                'division' => $row->division_name,
                'stage' => $row->stage_label,
                'total' => (int) $row->total,
            ]);
    }

    private function organizationBreakdown(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn(array $row) => $row['department'] . '|' . $row['division'])
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'department' => $first['department'],
                    'division' => $first['division'],
                    'pending_hod' => (int) $group->where('stage', 'HOD')->sum('total'),
                    'pending_hrd' => (int) $group->where('stage', 'HRD')->sum('total'),
                    'total' => (int) $group->sum('total'),
                ];
            })
            ->sortByDesc('total')
            ->take(8)
            ->values();
    }

    private function insights(array $summary, Collection $modules): Collection
    {
        $highestModule = $modules
            ->sortByDesc(fn(array $module) => $module['pending_hod'] + $module['pending_hrd'])
            ->first();

        return collect([
            [
                'title' => 'Prioritas HOD',
                'value' => $summary['pending_hod'],
                'description' => 'Pengajuan siap diproses HOD saat ini.',
                'class' => $summary['pending_hod'] > 0 ? 'danger' : 'success',
            ],
            [
                'title' => 'Lewat SLA',
                'value' => $summary['over_sla'],
                'description' => 'Pengajuan HOD melebihi SLA ' . (int) config('approval_sla.stages.hod.hours', 24) . ' jam.',
                'class' => $summary['over_sla'] > 0 ? 'warning' : 'success',
            ],
            [
                'title' => 'Tanggal Dekat',
                'value' => $summary['due_soon'] + $summary['effective_overdue'],
                'description' => 'Tanggal efektif lewat atau <= 7 hari.',
                'class' => ($summary['due_soon'] + $summary['effective_overdue']) > 0 ? 'primary' : 'secondary',
            ],
            [
                'title' => 'Terbanyak',
                'value' => $highestModule ? ($highestModule['pending_hod'] + $highestModule['pending_hrd']) : 0,
                'description' => $highestModule ? $highestModule['label'] : 'Belum ada antrean.',
                'class' => 'info',
            ],
        ]);
    }

    private function sortItems(Collection $items): Collection
    {
        return $items
            ->sortByDesc(fn(array $item) => $item['age_hours'] ?? 0)
            ->values();
    }

    private function primaryEffectiveColumn(array $definition): ?string
    {
        foreach ($this->availableEffectiveColumns($definition) as $column) {
            return $column;
        }

        return null;
    }

    private function availableEffectiveColumns(array $definition): array
    {
        $columns = [];

        foreach ($definition['effective_columns'] as $column) {
            if (Schema::hasColumn($definition['table'], $column)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    private function firstModelDate(Model $model, array $columns): ?Carbon
    {
        foreach ($columns as $column) {
            $date = $this->modelDate($model, $column);

            if ($date) {
                return $date;
            }
        }

        return null;
    }

    private function lastModelDate(Model $model, array $columns): ?Carbon
    {
        foreach (array_reverse($columns) as $column) {
            $date = $this->modelDate($model, $column);

            if ($date) {
                return $date;
            }
        }

        return $this->firstModelDate($model, $columns);
    }

    private function modelDate(Model $model, ?string $column): ?Carbon
    {
        if (!$column || !$model->{$column}) {
            return null;
        }

        return $model->{$column} instanceof Carbon
            ? $model->{$column}
            : Carbon::parse($model->{$column});
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
