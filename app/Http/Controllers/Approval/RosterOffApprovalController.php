<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\Controller;
use App\Http\Requests\Approval\ProcessApprovalRequest;
use App\Models\RosterOffRequest;
use App\Notifications\StatusPengajuanNotification;
use App\Services\Approvals\ApprovalAuditService;
use App\Services\Notifications\ApprovalNotificationService;
use App\Services\Presensi\AttendanceStatusService;
use Illuminate\Support\Facades\DB;

class RosterOffApprovalController extends Controller
{
    public function hodIndex()
    {
        $offRequests = auth()->user()->applyEmployeeRelationScope(
            RosterOffRequest::query()
                ->with('employee.divisi.departemen')
                ->orderByRaw('CASE WHEN status_hod = 0 THEN 0 WHEN status_hod = 1 THEN 1 ELSE 2 END')
                ->latest('tanggal_off')
        )->paginate(100)->withQueryString();

        return view('approval.hod.roster-off.index', compact('offRequests'));
    }

    public function hodProcess(ProcessApprovalRequest $request, $id)
    {
        $validated = $request->validated();
        $action = (int) $validated['action'];

        $result = DB::transaction(function () use ($request, $id, $action, $validated) {
            $offRequest = $request->user()
                ->applyEmployeeRelationScope(RosterOffRequest::query()->with(['user', 'employee']))
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $offRequest->status_hod !== RosterOffRequest::STATUS_PENDING) {
                return [
                    'status' => false,
                    'message' => 'Pengajuan OFF sudah diproses oleh HOD.',
                ];
            }

            $auditService = app(ApprovalAuditService::class);
            $oldValues = $auditService->approvalValues('roster_off_requests', $offRequest);

            $offRequest->update(array_merge([
                'status_hod' => $action,
                'hod_processed_by' => (string) $request->user()->id,
                'hod_processed_at' => now(),
            ], $auditService->payload(
                'roster_off_requests',
                'hod',
                $action,
                $request->user(),
                $validated['note'] ?? null
            )));

            $offRequest = $offRequest->fresh(['user', 'employee']);

            $auditService->record(
                'roster_off_requests',
                $offRequest,
                'hod',
                $action,
                $request->user(),
                $validated['note'] ?? null,
                $oldValues
            );

            return [
                'status' => true,
                'off_request' => $offRequest,
                'approval_status' => $action === RosterOffRequest::STATUS_APPROVED ? 'Disetujui' : 'Ditolak',
            ];
        });

        if (!$result['status']) {
            toast()->warning('Peringatan', $result['message']);
            return back();
        }

        $offRequest = $result['off_request'];
        app(AttendanceStatusService::class)->refreshRosterOff($offRequest);
        $this->notifyApplicant($offRequest, $result['approval_status'], 'HOD');

        if ($action === RosterOffRequest::STATUS_APPROVED) {
            app(ApprovalNotificationService::class)->notifyRosterOffWaitingForHr($offRequest);
        }

        toast()->success('Berhasil', 'Pengajuan OFF roster telah ' . strtolower($result['approval_status']) . ' oleh HOD.');
        return back();
    }

    public function hrdIndex()
    {
        $offRequests = auth()->user()->applyEmployeeRelationScope(
            RosterOffRequest::query()
                ->with('employee.divisi.departemen')
                ->where('status_hod', RosterOffRequest::STATUS_APPROVED)
                ->orderByRaw('CASE WHEN status_hrd = 0 THEN 0 WHEN status_hrd = 1 THEN 1 ELSE 2 END')
                ->latest('tanggal_off')
        )->paginate(100)->withQueryString();

        return view('approval.hr.roster-off.index', compact('offRequests'));
    }

    public function hrdProcess(ProcessApprovalRequest $request, $id)
    {
        $validated = $request->validated();
        $action = (int) $validated['action'];

        $result = DB::transaction(function () use ($request, $id, $action, $validated) {
            $offRequest = $request->user()
                ->applyEmployeeRelationScope(RosterOffRequest::query()->with(['user', 'employee']))
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $offRequest->status_hod !== RosterOffRequest::STATUS_APPROVED) {
                return [
                    'status' => false,
                    'message' => 'Pengajuan OFF belum disetujui HOD.',
                ];
            }

            if ((int) $offRequest->status_hrd !== RosterOffRequest::STATUS_PENDING) {
                return [
                    'status' => false,
                    'message' => 'Pengajuan OFF sudah diproses oleh HR.',
                ];
            }

            $auditService = app(ApprovalAuditService::class);
            $oldValues = $auditService->approvalValues('roster_off_requests', $offRequest);

            $offRequest->update(array_merge([
                'status_hrd' => $action,
                'hrd_processed_by' => (string) $request->user()->id,
                'hrd_processed_at' => now(),
            ], $auditService->payload(
                'roster_off_requests',
                'hrd',
                $action,
                $request->user(),
                $validated['note'] ?? null
            )));

            $offRequest = $offRequest->fresh(['user', 'employee']);

            $auditService->record(
                'roster_off_requests',
                $offRequest,
                'hrd',
                $action,
                $request->user(),
                $validated['note'] ?? null,
                $oldValues
            );

            return [
                'status' => true,
                'off_request' => $offRequest,
                'approval_status' => $action === RosterOffRequest::STATUS_APPROVED ? 'Disetujui' : 'Ditolak',
            ];
        });

        if (!$result['status']) {
            toast()->warning('Peringatan', $result['message']);
            return back();
        }

        $offRequest = $result['off_request'];
        app(AttendanceStatusService::class)->refreshRosterOff($offRequest);
        $this->notifyApplicant($offRequest, $result['approval_status'], 'HRD');

        toast()->success('Berhasil', 'Pengajuan OFF roster telah ' . strtolower($result['approval_status']) . ' oleh HRD.');
        return back();
    }

    private function notifyApplicant(RosterOffRequest $offRequest, string $status, string $approverLabel): void
    {
        $user = $offRequest->user;

        if (!$user) {
            return;
        }

        $user->notify(new StatusPengajuanNotification([
            'judul' => 'Pengajuan OFF Roster ' . $status,
            'pesan' => 'Pengajuan OFF roster tanggal ' . optional($offRequest->tanggal_off)->format('d M Y') . ' telah ' . strtolower($status) . ' oleh ' . $approverLabel . '.',
            'url' => route('roster-off.index'),
            'tipe' => 'OFF Roster',
        ]));
    }
}
