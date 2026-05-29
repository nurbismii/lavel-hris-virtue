<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Presensi\AttendanceAnomalyFilterRequest;
use App\Services\Presensi\AttendanceAnomalyService;

class AttendanceAnomalyDashboardController extends Controller
{
    public function index(AttendanceAnomalyFilterRequest $request, AttendanceAnomalyService $service)
    {
        $filters = $service->normalizeFilters($request->validated());
        $options = $service->filterOptions($request->user());
        $summary = $service->summary($request->user(), $filters);

        return view('admin.presensi.anomalies', [
            'filters' => $filters,
            'summary' => $summary,
            'anomalyTypes' => $service->anomalyTypes(),
            'areas' => $options['areas'],
            'departemens' => $options['departemens'],
            'divisis' => $options['divisis'],
        ]);
    }

    public function data(AttendanceAnomalyFilterRequest $request, AttendanceAnomalyService $service)
    {
        $filters = $service->normalizeFilters($request->validated());

        return response()->json(
            $service->dataTable($request->user(), $filters, $request->all())
        );
    }
}
