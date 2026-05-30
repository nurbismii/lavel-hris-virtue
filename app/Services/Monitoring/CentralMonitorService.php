<?php

namespace App\Services\Monitoring;

use App\Models\AttendancePeriodLock;
use App\Models\ImportHistory;
use App\Models\User;
use App\Services\Approvals\ApprovalSlaService;
use App\Services\Presensi\AttendanceAnomalyService;
use App\Services\Presensi\AttendancePeriodLockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CentralMonitorService
{
    public const STATUS_OK = 'ok';
    public const STATUS_WARNING = 'warning';
    public const STATUS_CRITICAL = 'critical';

    public function dashboard(User $user, array $filters = []): array
    {
        $periodMonth = $this->normalizePeriodMonth($filters['period_month'] ?? null);
        $periodService = app(AttendancePeriodLockService::class);
        $period = $periodService->periodForMonth($periodMonth);
        $periodSummary = $this->periodSummary($periodService, $period);
        $sla = $this->approvalSlaSummary();
        $attendance = $this->attendanceSummary($user, $period, $periodSummary);
        $imports = $this->importSummary();
        $queue = $this->queueSummary();
        $audit = $this->auditSummary();
        $closing = $this->closingSummary($period, $periodSummary);

        $sections = [
            'approval' => $this->approvalSection($periodSummary, $sla, $user),
            'attendance' => $this->attendanceSection($attendance, $closing, $period, $user),
            'imports' => $this->importSection($imports, $queue, $user),
            'audit' => $this->auditSection($audit, $user),
        ];

        return [
            'period' => [
                'key' => $period['period_key'],
                'month' => $period['period_key'],
                'label' => $period['label'],
                'start_date' => $period['start_date']->toDateString(),
                'end_date' => $period['end_date']->toDateString(),
            ],
            'health' => $this->health($sections),
            'sections' => $sections,
            'cards' => $this->cards($sections),
            'recent_imports' => $imports['recent'],
            'recent_audits' => $audit['recent'],
            'readiness' => [
                'attendance_period_locks' => Schema::hasTable('attendance_period_locks'),
                'approval_sla' => config('approval_sla.enabled', true),
                'approval_sla_logs' => Schema::hasTable('approval_sla_escalation_logs'),
                'attendance_anomaly' => $this->attendanceAnomalyReady(),
                'import_histories' => Schema::hasTable('import_histories'),
                'audit_trails' => Schema::hasTable('audit_trails'),
                'jobs' => Schema::hasTable('jobs'),
                'failed_jobs' => Schema::hasTable('failed_jobs'),
            ],
        ];
    }

    private function periodSummary(AttendancePeriodLockService $periodService, array $period): array
    {
        return $this->safeArray(function () use ($periodService, $period) {
            return $periodService->buildSummary(
                $period['start_date']->toDateString(),
                $period['end_date']->toDateString()
            );
        });
    }

    private function approvalSlaSummary(): array
    {
        if (!config('approval_sla.enabled', true)) {
            return [
                'enabled' => false,
                'total' => 0,
                'warning' => 0,
                'breached' => 0,
                'critical' => 0,
                'escalated' => 0,
            ];
        }

        return $this->safeArray(function () {
            $summary = app(ApprovalSlaService::class)->summary();

            return [
                'enabled' => true,
                'total' => (int) ($summary['total'] ?? 0),
                'warning' => (int) ($summary[ApprovalSlaService::STATUS_WARNING] ?? 0),
                'breached' => (int) ($summary[ApprovalSlaService::STATUS_BREACHED] ?? 0),
                'critical' => (int) ($summary[ApprovalSlaService::STATUS_CRITICAL] ?? 0),
                'escalated' => (int) ($summary['escalated'] ?? 0),
            ];
        }, [
            'enabled' => true,
            'total' => 0,
            'warning' => 0,
            'breached' => 0,
            'critical' => 0,
            'escalated' => 0,
        ]);
    }

    private function attendanceSummary(User $user, array $period, array $periodSummary): array
    {
        $filters = [
            'date_from' => $period['start_date']->toDateString(),
            'date_to' => $period['end_date']->toDateString(),
            'anomaly' => 'all',
        ];

        $anomalySummary = [];

        if ($this->attendanceAnomalyReady()) {
            $anomalySummary = $this->safeArray(function () use ($user, $filters) {
                $service = app(AttendanceAnomalyService::class);

                return $service->summary($user, $service->normalizeFilters($filters));
            });
        }

        return [
            'records' => (int) ($periodSummary['attendance_records'] ?? 0),
            'incomplete' => (int) ($periodSummary['incomplete_attendance_records'] ?? 0),
            'face_pending' => (int) ($periodSummary['pending_face_reviews'] ?? 0),
            'face_rejected' => (int) ($periodSummary['rejected_face_reviews'] ?? 0),
            'anomalies' => (int) ($anomalySummary['total'] ?? 0),
            'suspicious' => (int) ($anomalySummary['suspicious_score'] ?? 0),
        ];
    }

    private function closingSummary(array $period, array $periodSummary): array
    {
        $currentLock = null;

        if (Schema::hasTable('attendance_period_locks')) {
            $currentLock = AttendancePeriodLock::query()
                ->where('period_key', $period['period_key'])
                ->latest('id')
                ->first();
        }

        $blockers = collect([
            'incomplete_attendance_records',
            'pending_face_reviews',
            'rejected_face_reviews',
            'pending_cuti_hod',
            'pending_cuti_hrd',
            'pending_izin_hod',
            'pending_izin_hrd',
            'pending_roster_hod',
            'pending_roster_hrd',
            'pending_roster_off_hod',
            'pending_roster_off_hrd',
            'pending_attendance_correction_hod',
            'pending_attendance_correction_hrd',
            'pending_overtime_responses',
        ])->sum(fn(string $key) => (int) ($periodSummary[$key] ?? 0));

        return [
            'is_table_ready' => Schema::hasTable('attendance_period_locks'),
            'is_locked' => $currentLock ? $currentLock->is_locked : false,
            'status_label' => $currentLock ? $currentLock->status_label : 'Belum dikunci',
            'locked_at' => $currentLock && $currentLock->closed_at ? $currentLock->closed_at->format('d M Y H:i') : null,
            'blockers' => $blockers,
        ];
    }

    private function importSummary(): array
    {
        if (!Schema::hasTable('import_histories')) {
            return [
                'active' => 0,
                'failed_7d' => 0,
                'partial_7d' => 0,
                'recent' => collect(),
            ];
        }

        $since = now()->subDays(7);

        return [
            'active' => $this->countRows('import_histories', function ($query) {
                $query->whereIn('status', [ImportHistory::STATUS_QUEUED, ImportHistory::STATUS_PROCESSING]);
            }),
            'failed_7d' => $this->countRows('import_histories', function ($query) use ($since) {
                $query->where('status', ImportHistory::STATUS_FAILED)
                    ->where('created_at', '>=', $since);
            }),
            'partial_7d' => $this->countRows('import_histories', function ($query) use ($since) {
                $query->where('status', ImportHistory::STATUS_COMPLETED_WITH_ERRORS)
                    ->where('created_at', '>=', $since);
            }),
            'recent' => ImportHistory::query()
                ->select(['id', 'import_id', 'import_type', 'status', 'file_name', 'created_at', 'updated_at'])
                ->latest('created_at')
                ->latest('id')
                ->limit(5)
                ->get(),
        ];
    }

    private function queueSummary(): array
    {
        return [
            'pending_jobs' => $this->countRows('jobs'),
            'failed_jobs_7d' => $this->countRows('failed_jobs', function ($query) {
                if (Schema::hasColumn('failed_jobs', 'failed_at')) {
                    $query->where('failed_at', '>=', now()->subDays(7));
                }
            }),
        ];
    }

    private function auditSummary(): array
    {
        if (!Schema::hasTable('audit_trails')) {
            return [
                'last_24h' => 0,
                'approval_last_24h' => 0,
                'recent' => collect(),
            ];
        }

        $since = now()->subDay();

        return [
            'last_24h' => $this->countRows('audit_trails', fn($query) => $query->where('created_at', '>=', $since)),
            'approval_last_24h' => $this->countRows('audit_trails', function ($query) use ($since) {
                $query->where('created_at', '>=', $since)
                    ->where('module', 'approval');
            }),
            'recent' => DB::table('audit_trails')
                ->select(['id', 'created_at', 'event', 'module', 'actor_name', 'employee_nik'])
                ->latest('created_at')
                ->latest('id')
                ->limit(5)
                ->get(),
        ];
    }

    private function approvalSection(array $periodSummary, array $sla, User $user): array
    {
        $pendingHod = collect([
            'pending_cuti_hod',
            'pending_izin_hod',
            'pending_roster_hod',
            'pending_roster_off_hod',
            'pending_attendance_correction_hod',
        ])->sum(fn(string $key) => (int) ($periodSummary[$key] ?? 0));
        $pendingHr = collect([
            'pending_cuti_hrd',
            'pending_izin_hrd',
            'pending_roster_hrd',
            'pending_roster_off_hrd',
            'pending_attendance_correction_hrd',
        ])->sum(fn(string $key) => (int) ($periodSummary[$key] ?? 0));
        $slaCritical = $sla['critical'] + $sla['breached'];

        return [
            'title' => 'Approval & SLA',
            'icon' => 'fas fa-stopwatch',
            'status' => $slaCritical > 0 ? self::STATUS_CRITICAL : (($sla['warning'] + $pendingHod + $pendingHr) > 0 ? self::STATUS_WARNING : self::STATUS_OK),
            'primary_value' => $pendingHod + $pendingHr,
            'primary_label' => 'Approval pending',
            'description' => $slaCritical > 0
                ? $slaCritical . ' approval melewati SLA.'
                : 'Approval berjalan dalam batas normal.',
            'url' => $this->routeIfAccessible($user, 'approval_sla', 'approval-sla.index'),
            'metrics' => [
                ['label' => 'Pending HOD', 'value' => $pendingHod, 'tone' => $pendingHod > 0 ? 'warning' : 'ok'],
                ['label' => 'Pending HR', 'value' => $pendingHr, 'tone' => $pendingHr > 0 ? 'warning' : 'ok'],
                ['label' => 'Lewat SLA', 'value' => $sla['breached'], 'tone' => $sla['breached'] > 0 ? 'critical' : 'ok'],
                ['label' => 'Kritis', 'value' => $sla['critical'], 'tone' => $sla['critical'] > 0 ? 'critical' : 'ok'],
            ],
        ];
    }

    private function attendanceSection(array $attendance, array $closing, array $period, User $user): array
    {
        $critical = $attendance['face_rejected'] + ($closing['is_locked'] ? 0 : $closing['blockers']);
        $warning = $attendance['anomalies'] + $attendance['face_pending'];

        return [
            'title' => 'Presensi & Closing',
            'icon' => 'fas fa-user-clock',
            'status' => $critical > 0 ? self::STATUS_CRITICAL : ($warning > 0 ? self::STATUS_WARNING : self::STATUS_OK),
            'primary_value' => $attendance['anomalies'],
            'primary_label' => 'Anomali presensi',
            'description' => $closing['is_locked']
                ? 'Periode sedang dikunci.'
                : $closing['blockers'] . ' blocker sebelum closing.',
            'url' => $this->routeIfAccessible($user, 'attendance_anomaly', 'attendance-anomalies.index'),
            'metrics' => [
                ['label' => 'Baris presensi', 'value' => $attendance['records'], 'tone' => 'neutral'],
                ['label' => 'Jam belum lengkap', 'value' => $attendance['incomplete'], 'tone' => $attendance['incomplete'] > 0 ? 'warning' : 'ok'],
                ['label' => 'Review wajah', 'value' => $attendance['face_pending'], 'tone' => $attendance['face_pending'] > 0 ? 'warning' : 'ok'],
                ['label' => 'Ditolak wajah', 'value' => $attendance['face_rejected'], 'tone' => $attendance['face_rejected'] > 0 ? 'critical' : 'ok'],
            ],
            'secondary_url' => $this->routeIfAccessible($user, 'attendance_period_lock', 'attendance-period-locks.index', [
                'period_month' => $period['period_key'],
            ]),
        ];
    }

    private function importSection(array $imports, array $queue, User $user): array
    {
        $critical = $imports['failed_7d'] + $queue['failed_jobs_7d'];
        $warning = $imports['active'] + $imports['partial_7d'] + $queue['pending_jobs'];

        return [
            'title' => 'Import & Queue',
            'icon' => 'fas fa-file-import',
            'status' => $critical > 0 ? self::STATUS_CRITICAL : ($warning > 0 ? self::STATUS_WARNING : self::STATUS_OK),
            'primary_value' => $imports['active'] + $queue['pending_jobs'],
            'primary_label' => 'Proses aktif',
            'description' => $critical > 0
                ? $critical . ' kegagalan perlu dicek.'
                : 'Tidak ada kegagalan baru.',
            'url' => $this->routeIfAccessible($user, 'import_history', 'import-histories.index'),
            'metrics' => [
                ['label' => 'Import aktif', 'value' => $imports['active'], 'tone' => $imports['active'] > 0 ? 'warning' : 'ok'],
                ['label' => 'Import gagal 7 hari', 'value' => $imports['failed_7d'], 'tone' => $imports['failed_7d'] > 0 ? 'critical' : 'ok'],
                ['label' => 'Job pending', 'value' => $queue['pending_jobs'], 'tone' => $queue['pending_jobs'] > 0 ? 'warning' : 'ok'],
                ['label' => 'Job gagal 7 hari', 'value' => $queue['failed_jobs_7d'], 'tone' => $queue['failed_jobs_7d'] > 0 ? 'critical' : 'ok'],
            ],
        ];
    }

    private function auditSection(array $audit, User $user): array
    {
        return [
            'title' => 'Audit Aktivitas',
            'icon' => 'fas fa-clipboard-list',
            'status' => self::STATUS_OK,
            'primary_value' => $audit['last_24h'],
            'primary_label' => 'Aktivitas 24 jam',
            'description' => $audit['approval_last_24h'] . ' aktivitas approval tercatat.',
            'url' => $this->routeIfAccessible($user, 'audit_trail', 'audit-trails.index'),
            'metrics' => [
                ['label' => 'Audit 24 jam', 'value' => $audit['last_24h'], 'tone' => 'neutral'],
                ['label' => 'Approval 24 jam', 'value' => $audit['approval_last_24h'], 'tone' => 'neutral'],
            ],
        ];
    }

    private function cards(array $sections): array
    {
        return array_values($sections);
    }

    private function health(array $sections): array
    {
        $critical = collect($sections)->where('status', self::STATUS_CRITICAL)->count();
        $warning = collect($sections)->where('status', self::STATUS_WARNING)->count();
        $status = $critical > 0 ? self::STATUS_CRITICAL : ($warning > 0 ? self::STATUS_WARNING : self::STATUS_OK);

        return [
            'status' => $status,
            'label' => $this->statusLabel($status),
            'critical_count' => $critical,
            'warning_count' => $warning,
            'updated_at' => now()->format('d M Y H:i'),
        ];
    }

    private function statusLabel(string $status): string
    {
        if ($status === self::STATUS_CRITICAL) {
            return 'Perlu tindakan';
        }

        if ($status === self::STATUS_WARNING) {
            return 'Perlu dipantau';
        }

        return 'Normal';
    }

    private function attendanceAnomalyReady(): bool
    {
        return Schema::hasTable('employees')
            && Schema::hasTable('absensis');
    }

    private function countRows(string $table, ?callable $callback = null): int
    {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        return (int) $this->safe(function () use ($table, $callback) {
            $query = DB::table($table);

            if ($callback) {
                $callback($query);
            }

            return $query->count();
        }, 0);
    }

    private function routeIfAccessible(User $user, string $menu, string $route, array $parameters = []): ?string
    {
        if (!$user->hasMenuAccess($menu) || !Route::has($route)) {
            return null;
        }

        $parameters = array_filter($parameters, fn($value) => $value !== null && $value !== '');

        return route($route, $parameters);
    }

    private function normalizePeriodMonth(?string $periodMonth): string
    {
        if (!$periodMonth || !preg_match('/^\d{4}-\d{2}$/', $periodMonth)) {
            return now()->format('Y-m');
        }

        [$year, $month] = array_map('intval', explode('-', $periodMonth));

        if (!checkdate($month, 1, $year)) {
            return now()->format('Y-m');
        }

        return sprintf('%04d-%02d', $year, $month);
    }

    private function safeArray(callable $callback, array $fallback = []): array
    {
        return (array) $this->safe($callback, $fallback);
    }

    private function safe(callable $callback, $fallback = null)
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            report($exception);

            return $fallback;
        }
    }
}
