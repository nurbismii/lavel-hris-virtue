<?php

namespace App\Http\Controllers\Approval;

use App\Models\Cuti;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Notifications\StatusPengajuanNotification;

class IzinApprovalController extends Controller
{
    public function hodIndex()
    {
        $cutis = Cuti::select('cuti_izin.*')
            ->join('employees', 'cuti_izin.nik_karyawan', '=', 'employees.nik')
            ->whereIn('cuti_izin.tipe', ['PAID', 'UNPAID'])
            ->where('employees.divisi_id', auth()->user()->employee->divisi_id)
            ->orderByRaw("FIELD(cuti_izin.tipe, 'UNPAID', 'PAID')")
            ->with('employee') // tetap bisa eager load
            ->get();

        return view('approval.hod.izin.index', compact('cutis'));
    }

    public function hodProcess(Request $request, $id)
    {
        $cuti = Cuti::with('user')->findOrFail($id);

        if ($cuti->status_hod != 0) {
            return back()->with('error', 'Sudah diproses');
        }

        $cuti->update([
            'status_hod' => $request->action // 1 approve, 2 reject
        ]);

        $user = $cuti->user;

        $tipe = $cuti->tipe == 'PAID' ? '(Paid)' : '(Unpaid)';
        $status = $request->action == 1 ? 'Disetujui' : 'Ditolak';

        $user->notify(new StatusPengajuanNotification([
            'judul' => 'Pengajuan Izin ' . $tipe,
            'pesan' => 'Izin pada tanggal ' . $cuti->tanggal . '  telah ' . strtolower($status) . ' oleh HOD.',
            'url'   => route('izin.index'),
            'tipe'  => $tipe
        ]));

        toast()->success('Success', 'Cuti telah ' . strtolower($status) . ' oleh HOD');
        return back()->with('success', 'Berhasil diproses');
    }


    public function hrdIndex()
    {
        $cutis = Cuti::with('employee')
            ->where('status_hod', 1) // hanya yg sudah disetujui HOD
            ->whereIn('tipe', ['PAID', 'UNPAID'])
            ->orderByRaw("FIELD(tipe, 'UNPAID', 'PAID')")
            ->orderBy('tanggal', 'desc')
            ->get();

        return view('approval.hr.izin.index', compact('cutis'));
    }

    public function hrdProcess(Request $request, $id)
    {
        $cuti = Cuti::with('user')->findOrFail($id);

        if ($cuti->status_hod != 1) {
            return back()->with('error', 'Belum disetujui HOD');
        }

        if ($cuti->status_hrd == 1) {
            return back()->with('error', 'Sudah diproses');
        }

        $cuti->update([
            'status_hrd' => $request->action
        ]);

        // Jika disetujui HRD → potong sisa cuti
        if ($request->action == 1) {
            $cuti->employee->decrement('sisa_cuti', $cuti->jumlah);
        }

        $user = $cuti->user;

        $tipe = $cuti->tipe == 'PAID' ? '(Paid)' : '(Unpaid)';
        $status = $request->action == 1 ? 'Disetujui' : 'Ditolak';

        $user->notify(new StatusPengajuanNotification([
            'judul' => 'Pengajuan Izin ' . $tipe,
            'pesan' => 'Izin pada tanggal ' . $cuti->tanggal . '  telah ' . strtolower($status) . ' oleh HOD.',
            'url'   => route('izin.index'),
            'tipe'  => $tipe
        ]));

        toast()->success('Success', 'Cuti telah ' . strtolower($status) . ' oleh HR');
        return back();
    }
}
