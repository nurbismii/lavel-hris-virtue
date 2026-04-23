<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Cuti;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class IzinController extends Controller
{
    public function index()
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

        $data = Cuti::where('nik_karyawan', Auth::user()->nik_karyawan)
            ->whereIn('tipe', ['PAID', 'UNPAID'])
            ->latest('id')
            ->get();

        return view('user.izin.index', compact('data'));
    }

    public function create()
    {
        return view('user.izin.create');
    }

    public function show($id)
    {
        $this->findUserIzin($id);

        return redirect()->route('izin.index');
    }

    public function edit($id)
    {
        $izin = $this->findUserIzin($id);

        if (!$this->canManageIzin($izin)) {
            toast()->warning('Warning', 'Pengajuan izin yang sudah diproses tidak dapat diedit');
            return redirect()->route('izin.index');
        }

        return view('user.izin.edit', compact('izin'));
    }

    public function store(Request $request)
    {
        $STATUS_HOD = 0;
        $STATUS_HR = 0;
        $STATUS_PEMOHON = 1;

        $request->validate([
            'tipe' => 'required|in:PAID,UNPAID',
            'tanggal_mulai' => 'required|date',
            'tanggal_berakhir' => 'required|date|after_or_equal:tanggal_mulai',
            'keterangan' => 'nullable|string',
            'foto' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->tipe == 'PAID') {
            $request->validate([
                'tipe_izin' => 'required'
            ]);
        }

        $tanggalMulai = Carbon::parse($request->tanggal_mulai);
        $tanggalBerakhir = Carbon::parse($request->tanggal_berakhir);

        $jumlahHari = $tanggalMulai->diffInDays($tanggalBerakhir) + 1;

        $fotoPath = $this->storeFotoIzin($request, Auth::user()->nik_karyawan);

        Cuti::create([
            'nik_karyawan' => Auth::user()->nik_karyawan,
            'tanggal' => now(),
            'jumlah' => $jumlahHari,
            'tanggal_mulai' => $request->tanggal_mulai,
            'tanggal_berakhir' => $request->tanggal_berakhir,
            'keterangan' => $request->keterangan,
            'tipe' => $request->tipe,
            'status_pemohon' => $STATUS_PEMOHON, // 0 = Menunggu, 1 = Disetujui, 2 = Ditolak
            'status_hod' => $STATUS_HOD, // 0 = Menunggu, 1 = Disetujui, 2 = Ditolak
            'status_hrd' => $STATUS_HR, // 0 = Menunggu, 1 = Disetujui, 2 = Ditolak
            'foto' => $fotoPath,
        ]);

        toast()->success('Success', 'Pengajuan izin berhasil dikirim');
        return redirect()->route('izin.index');
    }

    public function update(Request $request, $id)
    {
        $izin = $this->findUserIzin($id);

        if (!$this->canManageIzin($izin)) {
            toast()->warning('Warning', 'Pengajuan izin yang sudah diproses tidak dapat diubah');
            return redirect()->route('izin.index');
        }

        $request->validate([
            'tipe' => 'required|in:PAID,UNPAID',
            'tanggal_mulai' => 'required|date',
            'tanggal_berakhir' => 'required|date|after_or_equal:tanggal_mulai',
            'keterangan' => 'nullable|string',
            'foto' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $tanggalMulai = Carbon::parse($request->tanggal_mulai);
        $tanggalBerakhir = Carbon::parse($request->tanggal_berakhir);
        $jumlahHari = $tanggalMulai->diffInDays($tanggalBerakhir) + 1;
        $fotoPath = $this->storeFotoIzin($request, Auth::user()->nik_karyawan, $izin->foto);

        $izin->update([
            'jumlah' => $jumlahHari,
            'tanggal_mulai' => $request->tanggal_mulai,
            'tanggal_berakhir' => $request->tanggal_berakhir,
            'keterangan' => $request->keterangan,
            'tipe' => $request->tipe,
            'foto' => $fotoPath,
        ]);

        toast()->success('Success', 'Pengajuan izin berhasil diperbarui');
        return redirect()->route('izin.index');
    }

    public function destroy($id)
    {
        $izin = $this->findUserIzin($id);

        if (!$this->canManageIzin($izin)) {
            toast()->warning('Warning', 'Pengajuan izin yang sudah diproses tidak dapat dihapus');
            return redirect()->route('izin.index');
        }

        $this->deleteFotoIzin($izin->foto);
        $izin->delete();

        toast()->success('Success', 'Pengajuan izin berhasil dihapus');
        return redirect()->route('izin.index');
    }

    private function findUserIzin($id): Cuti
    {
        return Cuti::where('nik_karyawan', Auth::user()->nik_karyawan)
            ->whereIn('tipe', ['PAID', 'UNPAID'])
            ->findOrFail($id);
    }

    private function canManageIzin(Cuti $izin): bool
    {
        return (int) $izin->status_hod === 0 && (int) $izin->status_hrd === 0;
    }

    private function storeFotoIzin(Request $request, string $nik, ?string $existingPath = null): string
    {
        if (!$request->hasFile('foto')) {
            return $existingPath ?: '-';
        }

        $this->deleteFotoIzin($existingPath);

        $destinationPath = public_path('izin/' . $nik);

        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0777, true);
        }

        $file = $request->file('foto');
        $filename = time() . '_' . $file->getClientOriginalName();
        $file->move($destinationPath, $filename);

        return 'izin/' . $nik . '/' . $filename;
    }

    private function deleteFotoIzin(?string $path): void
    {
        if (!$path || $path === '-') {
            return;
        }

        $fullPath = public_path($path);

        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }
}
