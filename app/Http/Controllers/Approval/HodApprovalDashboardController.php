<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\Controller;
use App\Services\Approvals\HodApprovalDashboardService;
use Illuminate\Http\Request;

class HodApprovalDashboardController extends Controller
{
    public function index(Request $request, HodApprovalDashboardService $dashboardService)
    {
        abort_unless(
            $request->user() && $request->user()->hasMenuAccess('approval_hod'),
            403,
            'Anda tidak memiliki akses ke dashboard approval HOD.'
        );

        return view('approval.hod.dashboard', [
            'dashboard' => $dashboardService->dashboard($request->user()),
        ]);
    }
}
