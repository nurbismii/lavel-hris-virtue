<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\Controller;
use App\Http\Requests\Approval\ProcessApprovalRequest;
use App\Models\Cuti;
use App\Models\Employee;
use App\Notifications\StatusPengajuanNotification;
use App\Services\Approvals\ApprovalAuditService;
use App\Services\Notifications\ApprovalNotificationService;
use App\Services\Presensi\AttendanceStatusService;
use Illuminate\Support\Facades\DB;

class CutiApprovalController extends Controller
{
    public function hodIndex()
    {
        $cutis = auth()->user()->applyEmployeeScope(
            Cuti::select('cuti_izin.*')
                ->join('employees', 'cuti_izin.nik_karyawan', '=', 'employees.nik')
                ->where('cuti_izin.tipe', 'CUTI')
                ->orderByRaw("FIELD(cuti_izin.status_hod, '0', '1')")
                ->with('employee'),
            'employees'
        )->paginate(100)->withQueryString();

        return view('approval.hod.cuti.index', compact('cutis'));
    }

    public function hodProcess(ProcessApprovalRequest $request, $id)
    {
        $validated = $request->validated();
        $action = (int) $validated['action'];

        $result = DB::transaction(function () use ($request, $id, $action, $validated) {
            $cuti = $request->user()
                ->applyEmployeeRelationScope(Cuti::query()->with(['user', 'employee']))
                ->where('tipe', 'CUTI')
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $cuti->status_hod !== 0) {
                return [
                    'status' => false,
                    'message' => 'Pengajuan sudah diproses oleh HOD.',
                ];
            }

            $cuti->update(array_merge([
                'status_hod' => $action,
            ], app(ApprovalAuditService::class)->payload(
                'cuti_izin',
                'hod',
                $action,
                $request->user(),
                $validated['note'] ?? null
            )));

            return [
                'status' => true,
                'cuti' => $cuti->fresh(['user', 'employee']),
                'approval_status' => $action === 1 ? 'Disetujui' : 'Ditolak',
            ];
        });

        if (!$result['status']) {
            toast()->warning('Peringatan', $result['message']);
            return back();
        }

        $cuti = $result['cuti'];
        app(AttendanceStatusService::class)->refreshCuti($cuti);

        $this->notifyApplicant($cuti, $result['approval_status'], 'HOD');

        if ($action === 1) {
            app(ApprovalNotificationService::class)->notifyCutiWaitingForHr($cuti);
        }

        toast()->success('Success', 'Cuti telah ' . strtolower($result['approval_status']) . ' oleh HOD');
        return back()->with('success', 'Berhasil diproses');
    }

    public function hrdIndex()
    {
        $cutis = auth()->user()->applyEmployeeRelationScope(
            Cuti::query()->with('employee')
                ->where('status_hod', 1)
                ->where('tipe', 'CUTI')
        )->paginate(100)->withQueryString();

        return view('approval.hr.cuti.index', compact('cutis'));
    }

    public function hrdProcess(ProcessApprovalRequest $request, $id)
    {
        $validated = $request->validated();
        $action = (int) $validated['action'];

        $result = DB::transaction(function () use ($request, $id, $action, $validated) {
            $cuti = $request->user()
                ->applyEmployeeRelationScope(Cuti::query()->with(['user', 'employee']))
                ->where('tipe', 'CUTI')
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

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

            $employee = Employee::query()
                ->where('nik', $cuti->nik_karyawan)
                ->lockForUpdate()
                ->first();

            if (!$employee) {
                return [
                    'status' => false,
                    'message' => 'Data karyawan tidak ditemukan.',
                ];
            }

            if ($action === 1 && (int) $employee->sisa_cuti < (int) $cuti->jumlah) {
                return [
                    'status' => false,
                    'message' => 'Sisa cuti karyawan tidak cukup untuk approval ini.',
                ];
            }

            $cuti->update(array_merge([
                'status_hrd' => $action,
            ], app(ApprovalAuditService::class)->payload(
                'cuti_izin',
                'hrd',
                $action,
                $request->user(),
                $validated['note'] ?? null
            )));

            if ($action === 1) {
                $employee->decrement('sisa_cuti', (int) $cuti->jumlah);
            }

            return [
                'status' => true,
                'cuti' => $cuti->fresh(['user', 'employee']),
                'approval_status' => $action === 1 ? 'Disetujui' : 'Ditolak',
            ];
        });

        if (!$result['status']) {
            toast()->warning('Peringatan', $result['message']);
            return back();
        }

        $cuti = $result['cuti'];
        app(AttendanceStatusService::class)->refreshCuti($cuti);

        $this->notifyApplicant($cuti, $result['approval_status'], 'HRD');

        toast()->success('Success', 'Cuti telah ' . strtolower($result['approval_status']) . ' oleh HR');
        return back();
    }

    private function notifyApplicant(Cuti $cuti, string $status, string $approverLabel): void
    {
        $user = $cuti->user;

        if (!$user) {
            return;
        }

        $user->notify(new StatusPengajuanNotification([
            'judul' => 'Pengajuan Cuti ' . $status,
            'pesan' => 'Cuti pada tanggal ' . $cuti->tanggal . ' telah ' . strtolower($status) . ' oleh ' . $approverLabel . '.',
            'url' => route('cuti.index'),
            'tipe' => 'Cuti Tahunan',
        ]));
    }
}
