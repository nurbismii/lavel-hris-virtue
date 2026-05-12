<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\Controller;
use App\Http\Requests\Approval\ProcessApprovalRequest;
use App\Models\AttendanceCorrection;
use App\Notifications\StatusPengajuanNotification;
use App\Services\Approvals\ApprovalDelegationService;
use App\Services\AttendanceCorrection\AttendanceCorrectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AttendanceCorrectionApprovalController extends Controller
{
    public function hodIndex(Request $request)
    {
        $isTableReady = $this->isFeatureReady();
        $corrections = null;

        if ($isTableReady) {
            $corrections = $request->user()
                ->applyEmployeeRelationScope(
                    app(ApprovalDelegationService::class)->restrictReadyForHod(
                        AttendanceCorrection::query()->with(['employee', 'requester:id,name,nik_karyawan']),
                        'attendance_corrections'
                    )
                )
                ->orderBy('status_hod')
                ->latest('created_at')
                ->paginate(50)
                ->withQueryString();
        }

        return view('approval.hod.attendance-corrections.index', [
            'corrections' => $corrections,
            'isTableReady' => $isTableReady,
        ]);
    }

    public function hodProcess(
        ProcessApprovalRequest $request,
        AttendanceCorrection $attendanceCorrection,
        AttendanceCorrectionService $service
    ) {
        $correction = $request->user()
            ->applyEmployeeRelationScope(
                app(ApprovalDelegationService::class)->restrictReadyForHod(
                    AttendanceCorrection::query()->with(['employee', 'requester:id,name,nik_karyawan']),
                    'attendance_corrections'
                )
            )
            ->whereKey($attendanceCorrection->getKey())
            ->firstOrFail();

        $validated = $request->validated();
        $result = $service->processHod(
            $correction,
            $request->user(),
            (int) $validated['action'],
            $validated['note'] ?? null
        );

        if (!$result['status']) {
            toast()->warning('Peringatan', $result['message']);
            return back();
        }

        $this->notifyApplicant($result['correction'], $result['approval_status'], 'HOD');

        toast()->success('Berhasil', $result['message']);
        return back();
    }

    public function hrdIndex(Request $request)
    {
        $isTableReady = $this->isFeatureReady();
        $corrections = null;

        if ($isTableReady) {
            $corrections = $request->user()
                ->applyEmployeeRelationScope(
                    AttendanceCorrection::query()->with(['employee', 'requester:id,name,nik_karyawan'])
                        ->where('status_hod', AttendanceCorrection::STATUS_APPROVED)
                )
                ->orderBy('status_hrd')
                ->latest('created_at')
                ->paginate(50)
                ->withQueryString();
        }

        return view('approval.hr.attendance-corrections.index', [
            'corrections' => $corrections,
            'isTableReady' => $isTableReady,
        ]);
    }

    public function hrdProcess(
        ProcessApprovalRequest $request,
        AttendanceCorrection $attendanceCorrection,
        AttendanceCorrectionService $service
    ) {
        $correction = $request->user()
            ->applyEmployeeRelationScope(
                AttendanceCorrection::query()->with(['employee', 'requester:id,name,nik_karyawan'])
            )
            ->whereKey($attendanceCorrection->getKey())
            ->firstOrFail();

        $validated = $request->validated();
        $result = $service->processHrd(
            $correction,
            $request->user(),
            (int) $validated['action'],
            $validated['note'] ?? null
        );

        if (!$result['status']) {
            toast()->warning('Peringatan', $result['message']);
            return back();
        }

        $this->notifyApplicant($result['correction'], $result['approval_status'], 'HRD');

        toast()->success('Berhasil', $result['message']);
        return back();
    }

    private function notifyApplicant(AttendanceCorrection $correction, string $status, string $approverLabel): void
    {
        $user = $correction->requester;

        if (!$user) {
            return;
        }

        $tanggal = optional($correction->tanggal)->format('d/m/Y');

        $user->notify(new StatusPengajuanNotification([
            'judul' => 'Pengajuan Presensi ' . $status,
            'pesan' => 'Pengajuan presensi tanggal ' . $tanggal . ' telah ' . strtolower($status) . ' oleh ' . $approverLabel . '.',
            'url' => route('attendance-corrections.index'),
            'tipe' => 'Pengajuan Presensi',
        ]));
    }

    private function isFeatureReady(): bool
    {
        return Schema::hasTable('attendance_corrections')
            && Schema::hasColumn('attendance_corrections', 'request_type')
            && Schema::hasColumn('absensis', 'partial_permission_type');
    }
}
