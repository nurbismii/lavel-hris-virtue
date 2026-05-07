<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AuditTrailController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'module' => ['nullable', 'string', 'max:80'],
            'event' => ['nullable', 'string', 'max:80'],
            'employee_nik' => ['nullable', 'string', 'max:32'],
            'actor' => ['nullable', 'string', 'max:150'],
        ]);

        $isTableReady = Schema::hasTable('audit_trails');
        $auditTrails = null;

        if ($isTableReady) {
            $auditTrails = AuditTrail::query()
                ->select([
                    'id',
                    'created_at',
                    'event',
                    'module',
                    'reference_table',
                    'reference_id',
                    'employee_nik',
                    'actor_id',
                    'actor_name',
                    'actor_role',
                    'ip_address',
                    'old_values',
                    'new_values',
                    'metadata',
                    'note',
                ])
                ->when($filters['date_from'] ?? null, function ($query, $dateFrom) {
                    $query->whereDate('created_at', '>=', $dateFrom);
                })
                ->when($filters['date_to'] ?? null, function ($query, $dateTo) {
                    $query->whereDate('created_at', '<=', $dateTo);
                })
                ->when($filters['module'] ?? null, function ($query, $module) {
                    $query->where('module', $module);
                })
                ->when($filters['event'] ?? null, function ($query, $event) {
                    $query->where('event', $event);
                })
                ->when($filters['employee_nik'] ?? null, function ($query, $nik) {
                    $query->where('employee_nik', 'like', $nik . '%');
                })
                ->when($filters['actor'] ?? null, function ($query, $actor) {
                    $query->where(function ($actorQuery) use ($actor) {
                        $actorQuery->where('actor_id', $actor)
                            ->orWhere('actor_name', 'like', $actor . '%');
                    });
                })
                ->latest('created_at')
                ->latest('id')
                ->paginate(50)
                ->withQueryString();
        }

        return view('admin.audit-trails.index', [
            'auditTrails' => $auditTrails,
            'eventLabels' => $this->eventLabels(),
            'filters' => $filters,
            'isTableReady' => $isTableReady,
            'moduleOptions' => [
                'approval' => 'Approval',
            ],
        ]);
    }

    private function eventLabels(): array
    {
        return [
            'approval.hod.approved' => 'HOD menyetujui',
            'approval.hod.rejected' => 'HOD menolak',
            'approval.hrd.approved' => 'HR menyetujui',
            'approval.hrd.rejected' => 'HR menolak',
        ];
    }
}
