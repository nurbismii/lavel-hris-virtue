<?php

namespace App\Services\Approvals;

use App\Models\AttendanceCorrection;
use App\Models\Cuti;
use App\Models\Roster;
use App\Models\RosterOffRequest;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ApprovalSidebarCountService
{
    private const EMPTY_COUNTS = [
        'cuti' => 0,
        'izin' => 0,
        'roster' => 0,
        'roster_off' => 0,
        'attendance_correction' => 0,
        'total' => 0,
    ];

    public function countsFor(User $user): array
    {
        $ttl = max(0, (int) config('access.approval_sidebar_cache_ttl', 30));

        if ($ttl === 0) {
            return $this->calculateCounts($user);
        }

        return Cache::remember($this->cacheKey($user), $ttl, function () use ($user) {
            return $this->calculateCounts($user);
        });
    }

    public function defaultCounts(): array
    {
        return [
            'approvalHodCounts' => self::EMPTY_COUNTS,
            'approvalHrCounts' => self::EMPTY_COUNTS,
        ];
    }

    private function calculateCounts(User $user): array
    {
        $approvalHodCounts = self::EMPTY_COUNTS;
        $approvalHrCounts = self::EMPTY_COUNTS;

        if ($user->hasMenuAccess('approval_hod')) {
            $approvalHodCounts['cuti'] = $user->applyEmployeeRelationScope(
                Cuti::query()
                    ->where('tipe', 'CUTI')
                    ->where('status_hod', 0)
            )->count();

            $approvalHodCounts['izin'] = $user->applyEmployeeRelationScope(
                Cuti::query()
                    ->whereIn('tipe', ['PAID', 'UNPAID'])
                    ->where('status_hod', 0)
            )->count();

            $approvalHodCounts['roster'] = $user->applyEmployeeRelationScope(
                Roster::query()
                    ->where('status_pengajuan', 0)
            )->count();

            if (Schema::hasTable('roster_off_requests')) {
                $approvalHodCounts['roster_off'] = $user->applyEmployeeRelationScope(
                    RosterOffRequest::query()
                        ->where('status_hod', RosterOffRequest::STATUS_PENDING)
                )->count();
            }

            if (Schema::hasTable('attendance_corrections')) {
                $approvalHodCounts['attendance_correction'] = $user->applyEmployeeRelationScope(
                    AttendanceCorrection::query()
                        ->where('status_hod', AttendanceCorrection::STATUS_PENDING)
                )->count();
            }

            $approvalHodCounts['total'] = $this->sumCounts($approvalHodCounts);
        }

        if ($user->hasMenuAccess('approval_hr')) {
            $approvalHrCounts['cuti'] = $user->applyEmployeeRelationScope(
                Cuti::query()
                    ->where('tipe', 'CUTI')
                    ->where('status_hod', 1)
                    ->where('status_hrd', 0)
            )->count();

            $approvalHrCounts['izin'] = $user->applyEmployeeRelationScope(
                Cuti::query()
                    ->whereIn('tipe', ['PAID', 'UNPAID'])
                    ->where('status_hod', 1)
                    ->where('status_hrd', 0)
            )->count();

            $approvalHrCounts['roster'] = $user->applyEmployeeRelationScope(
                Roster::query()
                    ->where('status_pengajuan', 1)
                    ->where('status_pengajuan_hrd', 0)
            )->count();

            if (Schema::hasTable('roster_off_requests')) {
                $approvalHrCounts['roster_off'] = $user->applyEmployeeRelationScope(
                    RosterOffRequest::query()
                        ->where('status_hod', RosterOffRequest::STATUS_APPROVED)
                        ->where('status_hrd', RosterOffRequest::STATUS_PENDING)
                )->count();
            }

            if (Schema::hasTable('attendance_corrections')) {
                $approvalHrCounts['attendance_correction'] = $user->applyEmployeeRelationScope(
                    AttendanceCorrection::query()
                        ->where('status_hod', AttendanceCorrection::STATUS_APPROVED)
                        ->where('status_hrd', AttendanceCorrection::STATUS_PENDING)
                )->count();
            }

            $approvalHrCounts['total'] = $this->sumCounts($approvalHrCounts);
        }

        return compact('approvalHodCounts', 'approvalHrCounts');
    }

    private function sumCounts(array $counts): int
    {
        return $counts['cuti']
            + $counts['izin']
            + $counts['roster']
            + $counts['roster_off']
            + $counts['attendance_correction'];
    }

    private function cacheKey(User $user): string
    {
        return 'approval-sidebar-counts:' . $user->getAuthIdentifier() . ':' . sha1(implode('|', [
            (string) $user->role_id,
            (string) optional($user->updated_at)->timestamp,
        ]));
    }
}
