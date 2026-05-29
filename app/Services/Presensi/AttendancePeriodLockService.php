<?php

namespace App\Services\Presensi;

use App\Models\AttendancePeriodLock;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendancePeriodLockService
{
    private const ATTENDANCE_COMPANY_CODES = ['VDNI', 'VDNIP'];

    public function periodForMonth(?string $periodMonth = null): array
    {
        $periodMonth = $this->normalizePeriodMonth($periodMonth);
        $base = Carbon::createFromFormat('Y-m-d', $periodMonth . '-01')->startOfDay();
        $start = $base->copy()->subMonthNoOverflow()->day(16)->startOfDay();
        $end = $base->copy()->day(15)->startOfDay();

        return [
            'period_key' => $base->format('Y-m'),
            'start_date' => $start,
            'end_date' => $end,
            'label' => $start->format('d M Y') . ' - ' . $end->format('d M Y'),
        ];
    }

    public function lockedPeriodForDate($date): ?AttendancePeriodLock
    {
        if (!$date || !$this->tableReady()) {
            return null;
        }

        $dateString = Carbon::parse($date)->toDateString();

        return AttendancePeriodLock::query()
            ->where('status', AttendancePeriodLock::STATUS_LOCKED)
            ->whereDate('start_date', '<=', $dateString)
            ->whereDate('end_date', '>=', $dateString)
            ->orderByDesc('id')
            ->first();
    }

    public function lockedPeriodForRange($startDate, $endDate): ?AttendancePeriodLock
    {
        if (!$startDate || !$endDate || !$this->tableReady()) {
            return null;
        }

        $start = Carbon::parse($startDate)->toDateString();
        $end = Carbon::parse($endDate)->toDateString();

        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        return AttendancePeriodLock::query()
            ->where('status', AttendancePeriodLock::STATUS_LOCKED)
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->orderByDesc('id')
            ->first();
    }

    public function guardDate($date, string $actionLabel): ?string
    {
        $lock = $this->lockedPeriodForDate($date);

        return $lock ? $this->lockMessage($lock, $actionLabel) : null;
    }

    public function guardRange($startDate, $endDate, string $actionLabel): ?string
    {
        $lock = $this->lockedPeriodForRange($startDate, $endDate);

        return $lock ? $this->lockMessage($lock, $actionLabel) : null;
    }

    public function guardRanges(array $ranges, string $actionLabel): ?string
    {
        foreach ($ranges as $range) {
            $start = $range[0] ?? null;
            $end = $range[1] ?? $start;

            if (!$start || !$end) {
                continue;
            }

            $message = $this->guardRange($start, $end, $actionLabel);

            if ($message) {
                return $message;
            }
        }

        return null;
    }

    public function guardRoster($roster, string $actionLabel): ?string
    {
        return $this->guardRanges($this->rosterRanges($roster), $actionLabel);
    }

    public function closePeriod(User $actor, string $periodMonth, ?string $note = null): array
    {
        $period = $this->periodForMonth($periodMonth);
        $summary = $this->buildSummary($period['start_date'], $period['end_date']);

        if ($this->summaryHasBlockers($summary)) {
            return [
                'status' => false,
                'message' => 'Periode belum bisa dikunci karena masih ada approval, review wajah, atau respons lembur yang belum selesai.',
                'summary' => $summary,
            ];
        }

        return DB::transaction(function () use ($actor, $period, $note, $summary) {
            $existingLocked = AttendancePeriodLock::query()
                ->where('status', AttendancePeriodLock::STATUS_LOCKED)
                ->where('period_key', '!=', $period['period_key'])
                ->whereDate('start_date', '<=', $period['end_date']->toDateString())
                ->whereDate('end_date', '>=', $period['start_date']->toDateString())
                ->lockForUpdate()
                ->first();

            if ($existingLocked) {
                return [
                    'status' => false,
                    'message' => 'Periode ini beririsan dengan periode ' . $existingLocked->period_label . ' yang sudah dikunci.',
                    'summary' => $summary,
                ];
            }

            $lock = AttendancePeriodLock::query()
                ->where('period_key', $period['period_key'])
                ->lockForUpdate()
                ->first();

            if ($lock && $lock->status === AttendancePeriodLock::STATUS_LOCKED) {
                return [
                    'status' => false,
                    'message' => 'Periode ' . $lock->period_label . ' sudah dalam status terkunci.',
                    'summary' => $summary,
                ];
            }

            $oldValues = $lock ? $lock->only([
                'status',
                'closed_by',
                'closed_at',
                'reopened_by',
                'reopened_at',
            ]) : [];

            if (!$lock) {
                $lock = new AttendancePeriodLock([
                    'period_key' => $period['period_key'],
                    'start_date' => $period['start_date']->toDateString(),
                    'end_date' => $period['end_date']->toDateString(),
                ]);
            }

            $lock->fill([
                'start_date' => $period['start_date']->toDateString(),
                'end_date' => $period['end_date']->toDateString(),
                'status' => AttendancePeriodLock::STATUS_LOCKED,
                'closed_by' => (string) $actor->id,
                'closed_at' => now(),
                'close_note' => $note,
                'reopened_by' => null,
                'reopened_at' => null,
                'reopen_note' => null,
                'summary' => $summary,
            ]);
            $lock->save();

            $this->recordAudit('attendance_period.closed', $lock, $actor, $oldValues, $this->auditValues($lock), $note);

            return [
                'status' => true,
                'message' => 'Periode presensi ' . $lock->period_label . ' berhasil dikunci.',
                'lock' => $lock,
                'summary' => $summary,
            ];
        });
    }

    public function reopenPeriod(AttendancePeriodLock $lock, User $actor, string $note): array
    {
        return DB::transaction(function () use ($lock, $actor, $note) {
            $lock = AttendancePeriodLock::query()
                ->whereKey($lock->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lock->status !== AttendancePeriodLock::STATUS_LOCKED) {
                return [
                    'status' => false,
                    'message' => 'Periode ini sudah dalam status dibuka ulang.',
                ];
            }

            $oldValues = $this->auditValues($lock);

            $lock->update([
                'status' => AttendancePeriodLock::STATUS_REOPENED,
                'reopened_by' => (string) $actor->id,
                'reopened_at' => now(),
                'reopen_note' => $note,
            ]);

            $this->recordAudit('attendance_period.reopened', $lock, $actor, $oldValues, $this->auditValues($lock), $note);

            return [
                'status' => true,
                'message' => 'Periode presensi ' . $lock->period_label . ' berhasil dibuka ulang.',
                'lock' => $lock,
            ];
        });
    }

    public function buildSummary($startDate, $endDate): array
    {
        $start = Carbon::parse($startDate)->toDateString();
        $end = Carbon::parse($endDate)->toDateString();

        return [
            'active_employees' => $this->activeEmployeeCount(),
            'attendance_records' => $this->countRows('absensis', function ($query) use ($start, $end) {
                $query->whereBetween('tanggal', [$start, $end]);
            }),
            'incomplete_attendance_records' => $this->countRows('absensis', function ($query) use ($start, $end) {
                $query->whereBetween('tanggal', [$start, $end])
                    ->whereNull('status_presensi')
                    ->where(function ($missing) {
                        $missing->whereNull('jam_masuk')
                            ->orWhereNull('jam_pulang');
                    });
            }),
            'pending_face_reviews' => $this->countRows('presensi_verifications', function ($query) use ($start, $end) {
                $query->whereBetween('tanggal', [$start, $end])
                    ->where('status', 'pending_review');
            }),
            'rejected_face_reviews' => $this->countRows('presensi_verifications', function ($query) use ($start, $end) {
                $query->whereBetween('tanggal', [$start, $end])
                    ->where('status', 'rejected');

                if (Schema::hasColumn('presensi_verifications', 'review_decision')) {
                    $query->whereNull('review_decision');
                }
            }),
            'pending_cuti_hod' => $this->pendingCutiCount($start, $end, ['CUTI'], 'hod'),
            'pending_cuti_hrd' => $this->pendingCutiCount($start, $end, ['CUTI'], 'hrd'),
            'pending_izin_hod' => $this->pendingCutiCount($start, $end, ['PAID', 'UNPAID'], 'hod'),
            'pending_izin_hrd' => $this->pendingCutiCount($start, $end, ['PAID', 'UNPAID'], 'hrd'),
            'pending_roster_hod' => $this->pendingRosterCount($start, $end, 'hod'),
            'pending_roster_hrd' => $this->pendingRosterCount($start, $end, 'hrd'),
            'pending_roster_off_hod' => $this->pendingRosterOffCount($start, $end, 'hod'),
            'pending_roster_off_hrd' => $this->pendingRosterOffCount($start, $end, 'hrd'),
            'pending_attendance_correction_hod' => $this->pendingAttendanceCorrectionCount($start, $end, 'hod'),
            'pending_attendance_correction_hrd' => $this->pendingAttendanceCorrectionCount($start, $end, 'hrd'),
            'pending_overtime_responses' => $this->countRows('overtime_orders', function ($query) use ($start, $end) {
                $query->whereBetween('overtime_date', [$start, $end])
                    ->where('employee_response_status', 'PENDING');
            }),
        ];
    }

    public function summaryHasBlockers(array $summary): bool
    {
        foreach ($this->blockerKeys() as $key) {
            if ((int) ($summary[$key] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    public function blockerKeys(): array
    {
        return [
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
        ];
    }

    public function tableReady(): bool
    {
        return Schema::hasTable('attendance_period_locks');
    }

    private function lockMessage(AttendancePeriodLock $lock, string $actionLabel): string
    {
        return $actionLabel . ' tidak dapat diproses karena periode presensi '
            . $lock->period_label
            . ' sudah dikunci. Buka ulang periode terlebih dahulu jika perubahan memang diperlukan.';
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

    private function rosterRanges($roster): array
    {
        $ranges = [
            [$roster->tgl_mulai_cuti ?? null, $roster->tgl_mulai_cuti_berakhir ?? null],
            [$roster->tgl_mulai_cuti_tahunan ?? null, $roster->tgl_mulai_cuti_tahunan_berakhir ?? null],
            [$roster->tgl_mulai_off ?? null, $roster->tgl_mulai_off_berakhir ?? null],
            [$roster->tgl_awal_kerja ?? null, $roster->tgl_akhir_kerja ?? null],
        ];

        $periode = $roster->relationLoaded('periodeKerjaRoster')
            ? $roster->periodeKerjaRoster
            : (method_exists($roster, 'periodeKerjaRoster') ? $roster->periodeKerjaRoster()->first() : null);

        if ($periode) {
            $ranges[] = [$periode->periode_awal ?? null, $periode->periode_akhir ?? null];
        }

        return $ranges;
    }

    private function activeEmployeeCount(): int
    {
        if (!Schema::hasTable('employees')) {
            return 0;
        }

        return DB::table('employees')
            ->where('status_resign', 'AKTIF')
            ->whereIn('area_kerja', self::ATTENDANCE_COMPANY_CODES)
            ->count();
    }

    private function pendingCutiCount(string $start, string $end, array $types, string $stage): int
    {
        return $this->countRows('cuti_izin', function ($query) use ($start, $end, $types, $stage) {
            $query->whereIn('tipe', $types)
                ->whereDate('tanggal_mulai', '<=', $end)
                ->whereDate('tanggal_berakhir', '>=', $start);

            $this->excludeRejectedDelegation($query, 'cuti_izin');

            if ($stage === 'hod') {
                $query->where('status_hod', 0);
            } else {
                $query->where('status_hod', 1)->where('status_hrd', 0);
            }
        });
    }

    private function pendingRosterCount(string $start, string $end, string $stage): int
    {
        return $this->countRows('cuti_roster', function ($query) use ($start, $end, $stage) {
            $this->applyRosterOverlap($query, $start, $end);
            $this->excludeRejectedDelegation($query, 'cuti_roster');

            if ($stage === 'hod') {
                $query->where('status_pengajuan', 0);
            } else {
                $query->where('status_pengajuan', 1)->where('status_pengajuan_hrd', 0);
            }
        });
    }

    private function pendingRosterOffCount(string $start, string $end, string $stage): int
    {
        return $this->countRows('roster_off_requests', function ($query) use ($start, $end, $stage) {
            $query->whereBetween('tanggal_off', [$start, $end]);
            $this->excludeRejectedDelegation($query, 'roster_off_requests');

            if ($stage === 'hod') {
                $query->where('status_hod', 0);
            } else {
                $query->where('status_hod', 1)->where('status_hrd', 0);
            }
        });
    }

    private function pendingAttendanceCorrectionCount(string $start, string $end, string $stage): int
    {
        return $this->countRows('attendance_corrections', function ($query) use ($start, $end, $stage) {
            $query->whereBetween('tanggal', [$start, $end]);
            $this->excludeRejectedDelegation($query, 'attendance_corrections');

            if ($stage === 'hod') {
                $query->where('status_hod', 0);
            } else {
                $query->where('status_hod', 1)->where('status_hrd', 0);
            }
        });
    }

    private function applyRosterOverlap($query, string $start, string $end): void
    {
        $query->where(function ($overlap) use ($start, $end) {
            foreach ([
                ['tgl_mulai_cuti', 'tgl_mulai_cuti_berakhir'],
                ['tgl_mulai_cuti_tahunan', 'tgl_mulai_cuti_tahunan_berakhir'],
                ['tgl_mulai_off', 'tgl_mulai_off_berakhir'],
                ['tgl_awal_kerja', 'tgl_akhir_kerja'],
            ] as $columns) {
                [$fromColumn, $toColumn] = $columns;

                $overlap->orWhere(function ($range) use ($fromColumn, $toColumn, $start, $end) {
                    $range->whereNotNull($fromColumn)
                        ->whereNotNull($toColumn)
                        ->whereDate($fromColumn, '<=', $end)
                        ->whereDate($toColumn, '>=', $start);
                });
            }
        });
    }

    private function excludeRejectedDelegation($query, string $table): void
    {
        if (!Schema::hasColumn($table, 'delegate_status')) {
            return;
        }

        $query->where(function ($delegateQuery) use ($table) {
            $delegateQuery->whereNull($table . '.delegate_status')
                ->orWhere($table . '.delegate_status', '!=', 2);
        });
    }

    private function countRows(string $table, callable $callback): int
    {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);
        $callback($query);

        return (int) $query->count();
    }

    private function recordAudit(
        string $event,
        AttendancePeriodLock $lock,
        User $actor,
        array $oldValues,
        array $newValues,
        ?string $note
    ): void {
        app(AuditTrailService::class)->record([
            'event' => $event,
            'module' => 'attendance_period_lock',
            'auditable_type' => AttendancePeriodLock::class,
            'auditable_id' => (string) $lock->id,
            'reference_table' => 'attendance_period_locks',
            'reference_id' => (string) $lock->id,
            'actor' => $actor,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => [
                'period_key' => $lock->period_key,
                'start_date' => optional($lock->start_date)->toDateString(),
                'end_date' => optional($lock->end_date)->toDateString(),
                'summary' => $lock->summary ?: [],
            ],
            'note' => $note,
        ]);
    }

    private function auditValues(AttendancePeriodLock $lock): array
    {
        return [
            'period_key' => $lock->period_key,
            'start_date' => optional($lock->start_date)->toDateString(),
            'end_date' => optional($lock->end_date)->toDateString(),
            'status' => $lock->status,
            'closed_by' => $lock->closed_by,
            'closed_at' => optional($lock->closed_at)->toDateTimeString(),
            'reopened_by' => $lock->reopened_by,
            'reopened_at' => optional($lock->reopened_at)->toDateTimeString(),
        ];
    }
}
