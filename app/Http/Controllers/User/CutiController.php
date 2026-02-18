<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Cuti;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CutiController extends Controller
{
    //
    public function index()
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

        return view('user.cuti.index', [
            'cuti' => Cuti::where('nik_karyawan', Auth::user()->nik_karyawan)->where('tipe', 'cuti')->get(),
        ]);
    }

    public function create()
    {
        $karyawan = Employee::where('nik', Auth::user()->nik_karyawan)
            ->select('nik', 'nama_karyawan', 'sisa_cuti')
            ->first();

        return view('user.cuti.create', [
            'karyawan' => $karyawan
        ]);
    }

    public function store(Request $request)
    {
        $result = app()->make(\App\Services\Cuti\CutiService::class)
            ->storeCuti($request);

        if (!$result['status']) {
            toast()->warning('Warning', $result['message']);
            return redirect()->route('cuti.index');
        }

        toast()->success('Success', $result['message']);
        return redirect()->route('cuti.index');
    }

    public function edit($id)
    {
        $APPROVE = 1;
        $REJECT = 2;

        $cuti = Cuti::findOrFail($id);
        $karyawan = $cuti->employee;

        if ($cuti->status_hod == $APPROVE || $cuti->status_hod == $REJECT) {
            toast()->warning('Warning', 'Perubahan cuti tidak dapat dilakukan, pengajuan telah ' . ($cuti->status_hod == $APPROVE ? 'disetujui' : 'ditolak'));
            return back();
        }

        return view('user.cuti.edit', compact('cuti', 'karyawan'));
    }

    public function update(Request $request, $nik_karyawan)
    {
        app()->make(\App\Services\Cuti\CutiService::class)->updateCuti($request, $nik_karyawan);

        toast()->success('Success', 'User updated successfully.');
        return redirect()->route('cuti.index');
    }

    public function destroy($id)
    {
        $APPROVE = 1;

        $cuti = Cuti::findOrFail($id)->delete();

        if ($cuti->status_hod == $APPROVE) {
            toast()->error('Error', 'Pengajuan cuti telah di approve, tidak dapat dihapus');
            return redirect()->route('cuti.index');
        }

        toast()->success('Success', 'Pengajuan cuti berhasil dihapus.');
        return redirect()->route('cuti.index');
    }
}
