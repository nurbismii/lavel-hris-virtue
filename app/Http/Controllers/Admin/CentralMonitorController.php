<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Monitoring\CentralMonitorService;
use Illuminate\Http\Request;

class CentralMonitorController extends Controller
{
    public function index(Request $request, CentralMonitorService $service)
    {
        $this->authorizeAccess($request->user());

        $filters = $request->validate([
            'period_month' => ['nullable', 'date_format:Y-m'],
        ]);

        return view('admin.central-monitor.index', [
            'dashboard' => $service->dashboard($request->user(), $filters),
            'filters' => $filters,
        ]);
    }

    private function authorizeAccess(User $user): void
    {
        abort_unless(
            $user->hasRole(['Super Admin', 'HR']) && $user->hasMenuAccess('central_monitor'),
            403,
            'Monitor terpusat hanya tersedia untuk Super Admin dan HR.'
        );
    }
}
