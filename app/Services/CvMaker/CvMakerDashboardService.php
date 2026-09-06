<?php

namespace App\Services\CvMaker;

use App\Models\CvMakerProgressStatus;
use Illuminate\Database\Eloquent\Builder;

class CvMakerDashboardService
{
    public const COMPANY_CODES = ['VDNI', 'VDNIP'];

    public function summarize(Builder $employees): array
    {
        // Reuse the scoped, filtered employee query; never fetch remote CV profiles here.
        $query = (clone $employees)->reorder()->select([])->setEagerLoads([])
            ->whereIn('employees.area_kerja', self::COMPANY_CODES)
            ->leftJoin('cv_maker_progress_statuses as dashboard_progress', 'dashboard_progress.employee_nik', '=', 'employees.nik');
        $summary = (clone $query)->selectRaw('COUNT(*) as total,
            SUM(CASE WHEN dashboard_progress.id IS NULL THEN 1 ELSE 0 END) as not_synced,
            SUM(CASE WHEN dashboard_progress.id IS NOT NULL AND dashboard_progress.cv_user_id IS NULL THEN 1 ELSE 0 END) as no_account,
            SUM(CASE WHEN dashboard_progress.cv_user_id IS NOT NULL AND dashboard_progress.cv_profile_id IS NULL THEN 1 ELSE 0 END) as no_profile,
            SUM(CASE WHEN dashboard_progress.cv_profile_id IS NOT NULL AND dashboard_progress.is_complete = 1 THEN 1 ELSE 0 END) as complete,
            SUM(CASE WHEN dashboard_progress.cv_profile_id IS NOT NULL AND dashboard_progress.is_complete = 0 THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN dashboard_progress.needs_reminder = 1 THEN 1 ELSE 0 END) as reminder,
            MIN(dashboard_progress.last_synced_at) as oldest_sync,
            MAX(dashboard_progress.last_synced_at) as latest_sync')->toBase()->first();

        $reviews = (clone $query)->whereNotNull('dashboard_progress.id')
            ->select('dashboard_progress.review_status')->selectRaw('COUNT(*) as total')
            ->groupBy('dashboard_progress.review_status')->toBase()->get()->pluck('total', 'review_status');

        $departments = (clone $query)
            ->leftJoin('departemens as dashboard_department', 'dashboard_department.id', '=', 'employees.departemen_id')
            ->select('employees.departemen_id', 'dashboard_department.departemen')
            ->selectRaw('COUNT(*) as total,
                SUM(CASE WHEN dashboard_progress.cv_profile_id IS NOT NULL AND dashboard_progress.is_complete = 1 THEN 1 ELSE 0 END) as complete')
            ->groupBy('employees.departemen_id', 'dashboard_department.departemen')
            ->orderByRaw('SUM(CASE WHEN dashboard_progress.cv_profile_id IS NOT NULL AND dashboard_progress.is_complete = 1 THEN 1 ELSE 0 END) * 1.0 / COUNT(*) ASC')
            ->orderByDesc('total')->limit(10)->toBase()->get();

        $steps = (clone $query)->whereNotNull('dashboard_progress.cv_profile_id')->where('dashboard_progress.is_complete', false)
            ->select('dashboard_progress.current_step', 'dashboard_progress.current_step_label')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('dashboard_progress.current_step', 'dashboard_progress.current_step_label')
            ->orderByDesc('total')->limit(8)->toBase()->get();

        $priorities = (clone $query)->where(function ($q) {
            $q->where('dashboard_progress.needs_reminder', true)
                ->orWhere('dashboard_progress.review_status', CvMakerProgressStatus::REVIEW_NEEDS_CONFIRMATION);
        })->select('employees.nik', 'employees.nama_karyawan', 'dashboard_progress.review_status',
            'dashboard_progress.needs_reminder', 'dashboard_progress.last_activity_at')
            ->orderBy('dashboard_progress.last_activity_at')->orderBy('employees.nik')->limit(8)->toBase()->get();

        return [
            'summary' => (array) $summary,
            'reviews' => collect(CvMakerProgressStatus::reviewLabels())->map(function ($label, $key) use ($reviews) {
                return ['key' => $key, 'label' => $label, 'total' => (int) ($reviews[$key] ?? 0)];
            })->values()->all(),
            'departments' => $departments,
            'steps' => $steps,
            'priorities' => $priorities->map(function ($row) {
                return [
                    'name' => $row->nama_karyawan,
                    'reason' => $row->review_status === CvMakerProgressStatus::REVIEW_NEEDS_CONFIRMATION
                        ? 'Perlu konfirmasi karyawan' : 'CV belum lengkap dan perlu reminder',
                    'last_activity' => $row->last_activity_at,
                    'url' => route('cv-maker-compare.show', $row->nik),
                ];
            }),
        ];
    }
}
