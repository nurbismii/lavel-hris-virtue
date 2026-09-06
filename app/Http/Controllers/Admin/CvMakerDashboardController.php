<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Departemen;
use App\Models\Employee;
use App\Services\CvMaker\CvMakerCompareService;
use App\Services\CvMaker\CvMakerDashboardService;
use Illuminate\Http\Request;

class CvMakerDashboardController extends Controller
{
    public function index(Request $request)
    {
        $employees = $request->user()->applyEmployeeScope(Employee::query(), 'employees')
            ->whereIn('employees.area_kerja', CvMakerDashboardService::COMPANY_CODES);

        return view('admin.cv-maker-dashboard.index', [
            'companies' => CvMakerDashboardService::COMPANY_CODES,
            'departments' => Departemen::query()->whereIn('id', $employees->select('employees.departemen_id'))
                ->orderBy('departemen')->get(['id', 'departemen']),
        ]);
    }

    public function data(Request $request, CvMakerCompareService $compare, CvMakerDashboardService $dashboard)
    {
        $filters = $request->validate([
            'area' => ['nullable', 'in:' . implode(',', CvMakerDashboardService::COMPANY_CODES)],
            'departemen' => ['nullable', 'integer'],
            'employment_status' => ['sometimes', 'required', 'in:active,inactive,all'],
            'cv_progress_status' => ['nullable', 'in:not_synced,no_account,no_profile,in_progress,complete'],
            'cv_review_status' => ['nullable', 'in:unreviewed,in_review,needs_employee_confirmation,completed'],
            'cv_reminder' => ['nullable', 'in:needs_reminder,not_needed'],
            'cv_progress_step' => ['nullable', 'array', 'max:8'],
            'cv_progress_step.*' => ['integer', 'between:1,8'],
        ]);

        $employees = $compare->filteredEmployeeQuery(new Request($filters), $request->user());
        $employmentStatus = $filters['employment_status'] ?? 'active';
        if ($employmentStatus !== 'all') {
            $employees->where('employees.status_resign', $employmentStatus === 'active' ? '=' : '<>', 'AKTIF');
        }

        $data = $dashboard->summarize($employees);
        if (!$request->user()->hasMenuAccess('cv_maker_compare')) {
            $data['priorities'] = $data['priorities']->map(function ($row) {
                $row['url'] = null;
                return $row;
            });
        }

        return response()->json(['success' => true, 'message' => 'Dashboard berhasil dimuat.', 'data' => $data]);
    }
}
