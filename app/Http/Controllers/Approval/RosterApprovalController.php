<?php

namespace App\Http\Controllers\Approval;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Roster;

class RosterApprovalController extends Controller
{
    public function hodIndex()
    {
        $cutis = Roster::with('employee', 'periodeKerjaRoster')->get();

        return view('approval.hod.roster.index', compact('cutis'));
    }

    public function hodProcess(Request $request, $id)
    {
        $cuti = Roster::findOrFail($id);

        if ($cuti->status_pengajuan != 0) {
            toast()->error('error', 'Sudah diproses');
            return back();
        }

        $cuti->update([
            'status_pengajuan' => $request->action // 1 approve, 2 reject
        ]);

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
        $cuti = Roster::findOrFail($id);

        if ($cuti->status_pengajuan != 1) {
            toast()->error('Error', 'Belum disetujui HOD');
            return back();
        }

        if ($cuti->status_pengajuan_hrd != 0) {
            toast()->error('Error', 'Sudah diproses');
            return back();
        }

        $cuti->update([
            'status_pengajuan_hrd' => $request->action
        ]);

        toast()->success('Success', 'Cuti roster telah diproses oleh HRD');
        return back();
    }
}
