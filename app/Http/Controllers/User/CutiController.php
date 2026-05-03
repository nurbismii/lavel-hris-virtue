<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cuti\CutiRequest;
use App\Models\Cuti;
use App\Models\Employee;
use App\Services\Notifications\ApprovalNotificationService;
use Illuminate\Support\Facades\Auth;

class CutiController extends Controller
{
    public function index()
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

        return view('user.cuti.index', [
            'cuti' => Cuti::with('employee')
                ->where('nik_karyawan', Auth::user()->nik_karyawan)
                ->where('tipe', 'CUTI')
                ->latest('id')
                ->get(),
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

    public function show($id)
    {
        $this->findUserCuti($id);

        return redirect()->route('cuti.index');
    }

    public function store(CutiRequest $request)
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
        $cuti = $this->findUserCuti($id);
        $karyawan = $cuti->employee;

        if (!$this->canManageCuti($cuti)) {
            toast()->warning('Warning', 'Pengajuan cuti yang sudah diproses tidak dapat diedit');
            return redirect()->route('cuti.index');
        }

        return view('user.cuti.edit', compact('cuti', 'karyawan'));
    }

    public function update(CutiRequest $request, $id)
    {
        $cuti = $this->findUserCuti($id);

        if (!$this->canManageCuti($cuti)) {
            toast()->warning('Warning', 'Pengajuan cuti yang sudah diproses tidak dapat diubah');
            return redirect()->route('cuti.index');
        }

        $result = app()->make(\App\Services\Cuti\CutiService::class)->updateCuti($request, $cuti);

        if (!$result['status']) {
            toast()->warning('Warning', $result['message']);
            return redirect()->route('cuti.index');
        }

        if (!empty($result['cuti'])) {
            app(ApprovalNotificationService::class)->notifyCutiSubmitted($result['cuti']);
        }

        toast()->success('Success', $result['message']);
        return redirect()->route('cuti.index');
    }

    public function destroy($id)
    {
        $cuti = $this->findUserCuti($id);

        if (!$this->canManageCuti($cuti)) {
            toast()->warning('Warning', 'Pengajuan cuti yang sudah diproses tidak dapat dihapus');
            return redirect()->route('cuti.index');
        }

        $cuti->delete();
        toast()->success('Success', 'Pengajuan cuti berhasil dihapus.');
        return redirect()->route('cuti.index');
    }

    private function findUserCuti($id): Cuti
    {
        return Cuti::with('employee')
            ->where('nik_karyawan', Auth::user()->nik_karyawan)
            ->where('tipe', 'CUTI')
            ->findOrFail($id);
    }

    private function canManageCuti(Cuti $cuti): bool
    {
        return (int) $cuti->status_hod === 0 && (int) $cuti->status_hrd === 0;
    }
}
