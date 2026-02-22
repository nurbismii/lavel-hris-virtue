<?php

namespace App\Http\Controllers\Approval;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Roster;
use App\Notifications\StatusPengajuanNotification;

class RosterApprovalController extends Controller
{
    public function hodIndex()
    {
        $cutis = $rosters = Roster::select('cuti_roster.*')
            ->join('employees', 'cuti_roster.nik_karyawan', '=', 'employees.nik')
            ->join('periode_kerja_roster', 'cuti_roster.id', '=', 'periode_kerja_roster.cuti_roster_id')
            ->where('employees.divisi_id', auth()->user()->employee->divisi_id)
            ->get();

        return view('approval.hod.roster.index', compact('cutis'));
    }

    public function hodProcess(Request $request, $id)
    {
        $cuti = Roster::with('user', 'periodeKerjaRoster')->findOrFail($id);

        if ($cuti->status_pengajuan == 1) {
            toast()->error('error', 'Sudah diproses');
            return back();
        }

        $cuti->update([
            'status_pengajuan' => $request->action // 1 approve, 2 reject
        ]);

        $user = $cuti->user;

        $status = $request->action == 1 ? 'Disetujui' : 'Ditolak';
        $tipeRencana = $cuti->periodeKerjaRoster->tipe_rencana == 1 ? 'Cuti Roster' : 'Insentif Roster';

        $user->notify(new StatusPengajuanNotification([
            'judul' => $tipeRencana,
            'pesan' => 'Roster pada tanggal ' . $cuti->tanggal_pengajuan . '  telah ' . strtolower($status) . ' oleh HOD.',
            'url'   => route('roster.index'),
            'tipe'  => $tipeRencana,
        ]));

        toast()->success('Success', 'Cuti roster telah diproses oleh HOD');
        return back();
    }

    public function hodShow($id)
    {
        $roster = Roster::with([
            'employee.divisi.departemen',
            'periodeKerjaRoster'
        ])->findOrFail($id);

        return view('approval.hod.roster.show', compact('roster'));
    }

    public function hrdIndex()
    {
        $cutis = Roster::with('employee', 'periodeKerjaRoster')
            ->where('status_pengajuan', 1) // sudah approve HOD
            ->orderBy('status_pengajuan', 'asc')
            ->get();

        return view('approval.hr.roster.index', compact('cutis'));
    }

    public function hrdProcess(Request $request, $id)
    {
        $cuti = Roster::with('user', 'periodeKerjaRoster')->findOrFail($id);

        if ($cuti->status_pengajuan != 1) {
            toast()->error('Error', 'Belum disetujui HOD');
            return back();
        }

        if ($cuti->status_pengajuan_hrd == 1) {
            toast()->error('Error', 'Sudah diproses');
            return back();
        }

        $cuti->update([
            'status_pengajuan_hrd' => $request->action
        ]);

        $user = $cuti->user;

        $status = $request->action == 1 ? 'Disetujui' : 'Ditolak';
        $tipeRencana = $cuti->periodeKerjaRoster->tipe_rencana == 1 ? 'Cuti Roster' : 'Insentif Roster';

        $user->notify(new StatusPengajuanNotification([
            'judul' => $tipeRencana,
            'pesan' => 'Roster pada tanggal ' . $cuti->tanggal_pengajuan . '  telah ' . strtolower($status) . ' oleh HOD.',
            'url'   => route('roster.index'),
            'tipe'  => $tipeRencana,
        ]));

        toast()->success('Success', 'Cuti roster telah diproses oleh HRD');
        return back();
    }
}
