<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\Controller;
use App\Http\Requests\Approval\ProcessApprovalRequest;
use App\Models\Roster;
use App\Notifications\StatusPengajuanNotification;
use App\Services\Presensi\AttendanceStatusService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class RosterApprovalController extends Controller
{
    public function hodIndex()
    {
        $cutis = auth()->user()->applyEmployeeScope(
            Roster::select('cuti_roster.*')
                ->join('employees', 'cuti_roster.nik_karyawan', '=', 'employees.nik')
                ->join('periode_kerja_roster', 'cuti_roster.id', '=', 'periode_kerja_roster.cuti_roster_id')
                ->with(['employee', 'periodeKerjaRoster']),
            'employees'
        )->get();

        return view('approval.hod.roster.index', compact('cutis'));
    }

    public function hodProcess(ProcessApprovalRequest $request, $id)
    {
        $action = (int) $request->validated()['action'];

        $result = DB::transaction(function () use ($request, $id, $action) {
            $roster = $request->user()
                ->applyEmployeeRelationScope(Roster::query()->with(['user', 'employee', 'periodeKerjaRoster']))
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $roster->status_pengajuan !== 0) {
                return [
                    'status' => false,
                    'message' => 'Pengajuan roster sudah diproses oleh HOD.',
                ];
            }

            $roster->update([
                'status_pengajuan' => $action,
            ]);

            return [
                'status' => true,
                'roster' => $roster->fresh(['user', 'employee', 'periodeKerjaRoster']),
                'approval_status' => $action === 1 ? 'Disetujui' : 'Ditolak',
            ];
        });

        if (!$result['status']) {
            toast()->warning('Peringatan', $result['message']);
            return back();
        }

        $roster = $result['roster'];
        app(AttendanceStatusService::class)->refreshRoster($roster);

        $this->notifyApplicant($roster, $result['approval_status'], 'HOD');

        toast()->success('Success', 'Cuti roster telah ' . strtolower($result['approval_status']) . ' oleh HOD');
        return back();
    }

    public function hodShow($id)
    {
        $roster = auth()->user()
            ->applyEmployeeRelationScope(
                Roster::query()->with([
                    'employee.divisi.departemen',
                    'periodeKerjaRoster'
                ])
            )
            ->findOrFail($id);

        return view('approval.hod.roster.show', compact('roster'));
    }

    public function hodAttachment($id)
    {
        $roster = auth()->user()
            ->applyEmployeeRelationScope(Roster::query())
            ->findOrFail($id);

        return $this->serveRosterAttachment($roster);
    }

    public function hrdIndex()
    {
        $cutis = auth()->user()->applyEmployeeRelationScope(
            Roster::query()->with('employee', 'periodeKerjaRoster')
                ->where('status_pengajuan', 1)
                ->orderBy('status_pengajuan', 'asc')
        )->get();

        return view('approval.hr.roster.index', compact('cutis'));
    }

    public function hrdShow($id)
    {
        $roster = auth()->user()
            ->applyEmployeeRelationScope(
                Roster::query()->with([
                    'employee.divisi.departemen',
                    'periodeKerjaRoster'
                ])->where('status_pengajuan', 1)
            )
            ->findOrFail($id);

        return view('approval.hr.roster.show', compact('roster'));
    }

    public function hrdAttachment($id)
    {
        $roster = auth()->user()
            ->applyEmployeeRelationScope(Roster::query()->where('status_pengajuan', 1))
            ->findOrFail($id);

        return $this->serveRosterAttachment($roster);
    }

    public function hrdProcess(ProcessApprovalRequest $request, $id)
    {
        $action = (int) $request->validated()['action'];

        $result = DB::transaction(function () use ($request, $id, $action) {
            $roster = $request->user()
                ->applyEmployeeRelationScope(Roster::query()->with(['user', 'employee', 'periodeKerjaRoster']))
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $roster->status_pengajuan !== 1) {
                return [
                    'status' => false,
                    'message' => 'Pengajuan roster belum disetujui HOD.',
                ];
            }

            if ((int) $roster->status_pengajuan_hrd !== 0) {
                return [
                    'status' => false,
                    'message' => 'Pengajuan roster sudah diproses oleh HR.',
                ];
            }

            $roster->update([
                'status_pengajuan_hrd' => $action,
            ]);

            return [
                'status' => true,
                'roster' => $roster->fresh(['user', 'employee', 'periodeKerjaRoster']),
                'approval_status' => $action === 1 ? 'Disetujui' : 'Ditolak',
            ];
        });

        if (!$result['status']) {
            toast()->warning('Peringatan', $result['message']);
            return back();
        }

        $roster = $result['roster'];
        app(AttendanceStatusService::class)->refreshRoster($roster);

        $this->notifyApplicant($roster, $result['approval_status'], 'HRD');

        toast()->success('Success', 'Cuti roster telah ' . strtolower($result['approval_status']) . ' oleh HRD');
        return back();
    }

    private function notifyApplicant(Roster $roster, string $status, string $approverLabel): void
    {
        $user = $roster->user;

        if (!$user) {
            return;
        }

        $tipeRencana = optional($roster->periodeKerjaRoster)->tipe_rencana == 1
            ? 'Cuti Roster'
            : 'Insentif Roster';

        $user->notify(new StatusPengajuanNotification([
            'judul' => $tipeRencana,
            'pesan' => 'Roster pada tanggal ' . $roster->tanggal_pengajuan . ' telah ' . strtolower($status) . ' oleh ' . $approverLabel . '.',
            'url' => route('roster.index'),
            'tipe' => $tipeRencana,
        ]));
    }

    private function serveRosterAttachment(Roster $roster)
    {
        abort_if(blank($roster->file), 404, 'Lampiran roster belum tersedia.');

        $filename = basename($roster->file);
        $absolutePath = public_path('cuti-roster/' . $roster->nik_karyawan . '/' . $filename);

        abort_unless(File::isFile($absolutePath), 404, 'Lampiran roster tidak ditemukan.');

        return response()->file($absolutePath, [
            'Content-Type' => File::mimeType($absolutePath) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
