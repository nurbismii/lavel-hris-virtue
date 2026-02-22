<?php

namespace App\Http\Controllers\Approval;

use App\Models\Cuti;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Notifications\StatusPengajuanNotification;

class CutiApprovalController extends Controller
{
    public function hodIndex()
    {
        $cutis = Cuti::select('cuti_izin.*')
            ->join('employees', 'cuti_izin.nik_karyawan', '=', 'employees.nik')
            ->where('cuti_izin.tipe', 'CUTI')
            ->where('employees.divisi_id', auth()->user()->employee->divisi_id)
            ->orderByRaw("FIELD(cuti_izin.status_hod, '0', '1')")
            ->with('employee') // tetap bisa eager load
            ->get();

        return view('approval.hod.cuti.index', compact('cutis'));
    }

    public function hodProcess(Request $request, $id)
    {
        $cuti = Cuti::with('user')->findOrFail($id);

        if ($cuti->status_hod == 1) {
            toast()->error('Error', 'Sudah diproses');
            return back();
        }

        $cuti->update([
            'status_hod' => $request->action // 1 approve, 2 reject
        ]);

        $user = $cuti->user;
        $status = $request->action == 1 ? 'Disetujui' : 'Ditolak';

        $user->notify(new StatusPengajuanNotification([
            'judul' => 'Pengajuan Cuti ' . $status,
            'pesan' => 'Cuti pada tanggal ' . $cuti->tanggal . '  telah ' . strtolower($status) . ' oleh HOD.',
            'url'   => route('cuti.index'),
            'tipe'  => 'Cuti Tahunan'
        ]));

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
        $cuti = Cuti::with('user')->findOrFail($id);

        if ($cuti->status_hod != 1) {
            toast()->error('error', 'Belum disetujui HOD');
            return back();
        }

        if ($cuti->status_hrd == 1) {
            toast()->error('error', 'Sudah diproses');
            return back();
        }

        $cuti->update([
            'status_hrd' => $request->action
        ]);

        // Jika disetujui HRD → potong sisa cuti
        if ($request->action == 1) {
            $cuti->employee->decrement('sisa_cuti', $cuti->jumlah);
        }

        $user = $cuti->user;
        $status = $request->action == 1 ? 'Disetujui' : 'Ditolak';

        $user->notify(new StatusPengajuanNotification([
            'judul' => 'Pengajuan Cuti ' . $status,
            'pesan' => 'Cuti pada tanggal ' . $cuti->tanggal . ' telah ' . strtolower($status) . ' oleh HRD.',
            'url'   => route('cuti.index'),
            'tipe'  => 'Cuti Tahunan'
        ]));

        toast()->success('Success', 'Cuti telah disetujui oleh HR');
        return back();
    }
}
