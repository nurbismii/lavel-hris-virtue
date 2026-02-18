<?php

namespace App\Http\Controllers\Approval;

use App\Models\Cuti;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CutiApprovalController extends Controller
{
    public function hodIndex()
    {
        $cutis = Cuti::with('employee')->where('tipe', 'CUTI')
            ->orderByRaw("FIELD(status_hod, '0', '1')")
            ->get();

        return view('approval.hod.cuti.index', compact('cutis'));
    }

    public function hodProcess(Request $request, $id)
    {
        $cuti = Cuti::findOrFail($id);

        if ($cuti->status_hod != 0) {
            return back()->with('error', 'Sudah diproses');
        }

        $cuti->update([
            'status_hod' => $request->action // 1 approve, 2 reject
        ]);

        toast()->success('Success', 'Cuti telah disetujui oleh HOD');
        return back()->with('success', 'Berhasil diproses');
    }


    public function hrdIndex()
    {
        $cutis = Cuti::with('employee')
            ->where('status_hod', 1) // hanya yg sudah disetujui HOD
            ->where('tipe', 'CUTI')
            ->get();

        return view('approval.hr.cuti.index', compact('cutis'));
    }

    public function hrdProcess(Request $request, $id)
    {
        $cuti = Cuti::findOrFail($id);

        if ($cuti->status_hod != 1) {
            return back()->with('error', 'Belum disetujui HOD');
        }

        if ($cuti->status_hrd != 0) {
            return back()->with('error', 'Sudah diproses');
        }

        $cuti->update([
            'status_hrd' => $request->action
        ]);

        // Jika disetujui HRD → potong sisa cuti
        if ($request->action == 1) {
            $cuti->employee->decrement('sisa_cuti', $cuti->jumlah);
        }

        toast()->success('Success', 'Cuti telah disetujui oleh HR');
        return back();
    }
}
