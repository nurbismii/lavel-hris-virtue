<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\Controller;
use App\Http\Requests\Approval\ProcessApprovalRequest;
use App\Models\Cuti;
use App\Notifications\StatusPengajuanNotification;
use App\Services\Approvals\ApprovalAuditService;
use App\Services\Approvals\ApprovalDelegationService;
use App\Services\Notifications\ApprovalNotificationService;
use App\Services\Presensi\AttendancePeriodLockService;
use App\Services\Presensi\AttendanceStatusService;
use Illuminate\Support\Facades\DB;
use Throwable;

class IzinApprovalController extends Controller
{
    public function hodIndex()
    {
        $delegationService = app(ApprovalDelegationService::class);
        $query = $delegationService->restrictReadyForHod(
            Cuti::select('cuti_izin.*')
                ->join('employees', 'cuti_izin.nik_karyawan', '=', 'employees.nik')
                ->whereIn('cuti_izin.tipe', ['PAID', 'UNPAID'])
                ->orderByRaw("FIELD(cuti_izin.tipe, 'UNPAID', 'PAID')")
                ->with('employee'),
            'cuti_izin'
        );

        $cutis = auth()->user()->applyEmployeeScope(
            $query,
            'employees'
        )->paginate(100)->withQueryString();

        return view('approval.hod.izin.index', compact('cutis'));
    }

    public function hodProcess(ProcessApprovalRequest $request, $id)
    {
        $validated = $request->validated();
        $action = (int) $validated['action'];

        try {
            $result = DB::transaction(function () use ($request, $id, $action, $validated) {
                $cuti = $request->user()
                    ->applyEmployeeRelationScope(Cuti::query()->with(['user', 'employee']))
                    ->whereIn('tipe', ['PAID', 'UNPAID'])
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->first();

                if (!$cuti) {
                    return [
                        'status' => false,
                        'message' => 'Pengajuan izin tidak ditemukan atau berada di luar akses Anda.',
                    ];
                }

                if ((int) $cuti->status_hod !== 0) {
                    return [
                        'status' => false,
                        'message' => 'Pengajuan sudah diproses oleh HOD.',
                    ];
                }

                if (app(ApprovalDelegationService::class)->blocksHodApproval($cuti, 'cuti_izin')) {
                    return [
                        'status' => false,
                        'message' => 'Pengajuan masih menunggu atau sudah ditolak pada tahap delegasi.',
                    ];
                }

                $periodLockMessage = app(AttendancePeriodLockService::class)->guardRange(
                    $cuti->tanggal_mulai,
                    $cuti->tanggal_berakhir,
                    'Approval izin'
                );

                if ($periodLockMessage) {
                    return [
                        'status' => false,
                        'message' => $periodLockMessage,
                    ];
                }

                $auditService = app(ApprovalAuditService::class);
                $oldValues = $auditService->approvalValues('cuti_izin', $cuti);

                $cuti->update(array_merge([
                    'status_hod' => $action,
                ], $auditService->payload(
                    'cuti_izin',
                    'hod',
                    $action,
                    $request->user(),
                    $validated['note'] ?? null
                )));

                $cuti = $cuti->fresh(['user', 'employee']);

                $auditService->record(
                    'cuti_izin',
                    $cuti,
                    'hod',
                    $action,
                    $request->user(),
                    $validated['note'] ?? null,
                    $oldValues
                );

                return [
                    'status' => true,
                    'cuti' => $cuti,
                    'approval_status' => $action === 1 ? 'Disetujui' : 'Ditolak',
                ];
            });
        } catch (Throwable $exception) {
            report($exception);

            return $this->approvalFailureResponse(
                'Approval izin gagal diproses. Silakan coba lagi atau hubungi admin sistem.'
            );
        }

        if (!$result['status']) {
            return $this->approvalWarningResponse($result['message']);
        }

        $cuti = $result['cuti'];
        try {
            app(AttendanceStatusService::class)->refreshIzin($cuti);
            $this->notifyApplicant($cuti, $result['approval_status'], 'HOD');

            if ($action === 1) {
                app(ApprovalNotificationService::class)->notifyIzinWaitingForHr($cuti);
            }
        } catch (Throwable $exception) {
            report($exception);

            return $this->approvalWarningResponse(
                'Approval izin tersimpan, tetapi sinkronisasi presensi atau notifikasi perlu dicek admin.'
            );
        }

        return $this->approvalSuccessResponse('Izin telah ' . strtolower($result['approval_status']) . ' oleh HOD.');
    }

    public function hrdIndex()
    {
        $cutis = auth()->user()->applyEmployeeRelationScope(
            Cuti::query()->with('employee')
                ->where('status_hod', 1)
                ->whereIn('tipe', ['PAID', 'UNPAID'])
                ->orderByRaw("FIELD(tipe, 'UNPAID', 'PAID')")
                ->orderBy('tanggal', 'desc')
        )->paginate(100)->withQueryString();

        return view('approval.hr.izin.index', compact('cutis'));
    }

    public function hrdProcess(ProcessApprovalRequest $request, $id)
    {
        $validated = $request->validated();
        $action = (int) $validated['action'];

        try {
            $result = DB::transaction(function () use ($request, $id, $action, $validated) {
                $cuti = $request->user()
                    ->applyEmployeeRelationScope(Cuti::query()->with(['user', 'employee']))
                    ->whereIn('tipe', ['PAID', 'UNPAID'])
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->first();

                if (!$cuti) {
                    return [
                        'status' => false,
                        'message' => 'Pengajuan izin tidak ditemukan atau berada di luar akses Anda.',
                    ];
                }

                if ((int) $cuti->status_hod !== 1) {
                    return [
                        'status' => false,
                        'message' => 'Pengajuan belum disetujui HOD.',
                    ];
                }

                if ((int) $cuti->status_hrd !== 0) {
                    return [
                        'status' => false,
                        'message' => 'Pengajuan sudah diproses oleh HR.',
                    ];
                }

                $periodLockMessage = app(AttendancePeriodLockService::class)->guardRange(
                    $cuti->tanggal_mulai,
                    $cuti->tanggal_berakhir,
                    'Approval izin'
                );

                if ($periodLockMessage) {
                    return [
                        'status' => false,
                        'message' => $periodLockMessage,
                    ];
                }

                $auditService = app(ApprovalAuditService::class);
                $oldValues = $auditService->approvalValues('cuti_izin', $cuti);

                $cuti->update(array_merge([
                    'status_hrd' => $action,
                ], $auditService->payload(
                    'cuti_izin',
                    'hrd',
                    $action,
                    $request->user(),
                    $validated['note'] ?? null
                )));

                $cuti = $cuti->fresh(['user', 'employee']);

                $auditService->record(
                    'cuti_izin',
                    $cuti,
                    'hrd',
                    $action,
                    $request->user(),
                    $validated['note'] ?? null,
                    $oldValues
                );

                return [
                    'status' => true,
                    'cuti' => $cuti,
                    'approval_status' => $action === 1 ? 'Disetujui' : 'Ditolak',
                ];
            });
        } catch (Throwable $exception) {
            report($exception);

            return $this->approvalFailureResponse(
                'Approval izin gagal diproses. Silakan coba lagi atau hubungi admin sistem.'
            );
        }

        if (!$result['status']) {
            return $this->approvalWarningResponse($result['message']);
        }

        $cuti = $result['cuti'];
        try {
            app(AttendanceStatusService::class)->refreshIzin($cuti);
            $this->notifyApplicant($cuti, $result['approval_status'], 'HR');
        } catch (Throwable $exception) {
            report($exception);

            return $this->approvalWarningResponse(
                'Approval izin tersimpan, tetapi sinkronisasi presensi atau notifikasi perlu dicek admin.'
            );
        }

        return $this->approvalSuccessResponse('Izin telah ' . strtolower($result['approval_status']) . ' oleh HR.');
    }

    private function approvalSuccessResponse(string $message)
    {
        toast()->success('Success', $message);

        return back()->with('success', $message);
    }

    private function approvalWarningResponse(string $message)
    {
        toast()->warning('Peringatan', $message);

        return back()->with('warning', $message);
    }

    private function approvalFailureResponse(string $message)
    {
        toast()->error('Gagal', $message);

        return back()->withInput()->with('error', $message);
    }

    private function notifyApplicant(Cuti $cuti, string $status, string $approverLabel): void
    {
        $user = $cuti->user;

        if (!$user) {
            return;
        }

        $tipe = $cuti->tipe === 'PAID' ? '(Paid)' : '(Unpaid)';

        $user->notify(new StatusPengajuanNotification([
            'judul' => 'Pengajuan Izin ' . $tipe,
            'pesan' => 'Izin pada tanggal ' . $cuti->tanggal . ' telah ' . strtolower($status) . ' oleh ' . $approverLabel . '.',
            'url' => route('izin.index'),
            'tipe' => $tipe,
        ]));
    }
}
