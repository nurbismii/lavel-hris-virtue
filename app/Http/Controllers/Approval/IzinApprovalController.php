<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\Controller;
use App\Http\Requests\Approval\ProcessApprovalRequest;
use App\Models\Cuti;
use App\Notifications\StatusPengajuanNotification;
use App\Services\Notifications\ApprovalNotificationService;
use App\Services\Presensi\AttendanceStatusService;
use Illuminate\Support\Facades\DB;

class IzinApprovalController extends Controller
{
    public function hodIndex()
    {
        $cutis = auth()->user()->applyEmployeeScope(
            Cuti::select('cuti_izin.*')
                ->join('employees', 'cuti_izin.nik_karyawan', '=', 'employees.nik')
                ->whereIn('cuti_izin.tipe', ['PAID', 'UNPAID'])
                ->orderByRaw("FIELD(cuti_izin.tipe, 'UNPAID', 'PAID')")
                ->with('employee'),
            'employees'
        )->get();

        return view('approval.hod.izin.index', compact('cutis'));
    }

    public function hodProcess(ProcessApprovalRequest $request, $id)
    {
        $action = (int) $request->validated()['action'];

        $result = DB::transaction(function () use ($request, $id, $action) {
            $cuti = $request->user()
                ->applyEmployeeRelationScope(Cuti::query()->with(['user', 'employee']))
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $cuti->status_hod !== 0) {
                return [
                    'status' => false,
                    'message' => 'Pengajuan sudah diproses oleh HOD.',
                ];
            }

            $cuti->update([
                'status_hod' => $action,
            ]);

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
        app(AttendanceStatusService::class)->refreshIzin($cuti);

        $this->notifyApplicant($cuti, $result['approval_status'], 'HOD');

        if ($action === 1) {
            app(ApprovalNotificationService::class)->notifyIzinWaitingForHr($cuti);
        }

        toast()->success('Success', 'Izin telah ' . strtolower($result['approval_status']) . ' oleh HOD');
        return back()->with('success', 'Berhasil diproses');
    }

    public function hrdIndex()
    {
        $cutis = auth()->user()->applyEmployeeRelationScope(
            Cuti::query()->with('employee')
                ->where('status_hod', 1)
                ->whereIn('tipe', ['PAID', 'UNPAID'])
                ->orderByRaw("FIELD(tipe, 'UNPAID', 'PAID')")
                ->orderBy('tanggal', 'desc')
        )->get();

        return view('approval.hr.izin.index', compact('cutis'));
    }

    public function hrdProcess(ProcessApprovalRequest $request, $id)
    {
        $action = (int) $request->validated()['action'];

        $result = DB::transaction(function () use ($request, $id, $action) {
            $cuti = $request->user()
                ->applyEmployeeRelationScope(Cuti::query()->with(['user', 'employee']))
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

            $cuti->update([
                'status_hrd' => $action,
            ]);

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
        app(AttendanceStatusService::class)->refreshIzin($cuti);

        $this->notifyApplicant($cuti, $result['approval_status'], 'HR');

        toast()->success('Success', 'Izin telah ' . strtolower($result['approval_status']) . ' oleh HR');
        return back();
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
