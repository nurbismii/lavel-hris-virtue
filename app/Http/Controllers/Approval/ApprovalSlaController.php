<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\Controller;
use App\Models\ApprovalSlaEscalationLog;
use App\Models\User;
use App\Services\Approvals\ApprovalSlaService;
use Illuminate\Http\Request;

class ApprovalSlaController extends Controller
{
    public function index(Request $request, ApprovalSlaService $service)
    {
        $this->authorizeAccess($request->user());

        $validated = $request->validate([
            'module' => ['nullable', 'string', 'max:50'],
            'stage' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        $filters = $service->normalizeFilters($validated);
        $items = $service->pendingItems($filters, (int) config('approval_sla.dashboard_limit', 500));
        $logs = $service->tableReady()
            ? ApprovalSlaEscalationLog::query()
                ->with('escalator:id,name')
                ->latest('escalated_at')
                ->latest('id')
                ->limit(20)
                ->get()
            : collect();

        return view('approval.sla.index', [
            'filters' => $filters,
            'items' => $items,
            'summary' => $service->summarizeItems($items),
            'modules' => $service->modules(),
            'stages' => $service->stages(),
            'statuses' => $service->statuses(),
            'logs' => $logs,
            'isTableReady' => $service->tableReady(),
        ]);
    }

    public function escalate(Request $request, ApprovalSlaService $service)
    {
        $this->authorizeAccess($request->user());

        if (!$service->tableReady()) {
            toast()->warning('Peringatan', 'Tabel log SLA approval belum tersedia. Jalankan migrate terlebih dahulu.');

            return back();
        }

        $result = $service->escalateOverdue($request->user());

        if ($result['created'] > 0) {
            toast()->success(
                'Berhasil',
                $result['created'] . ' eskalasi SLA approval dikirim. ' . $result['skipped'] . ' item dilewati.'
            );
        } else {
            toast()->info('Informasi', 'Tidak ada eskalasi baru. Semua item overdue sudah pernah dieskalasi atau belum punya penerima.');
        }

        return redirect()->route('approval-sla.index', $request->only(['module', 'stage', 'status']));
    }

    private function authorizeAccess(User $user): void
    {
        abort_unless(
            $user->hasRole(['Super Admin', 'HR']) && $user->hasMenuAccess('approval_sla'),
            403,
            'Menu SLA approval hanya tersedia untuk Super Admin dan HR.'
        );
    }
}
