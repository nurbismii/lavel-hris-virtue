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
        $data = Cuti::where('nik_karyawan', Auth::user()->nik_karyawan)
            ->whereIn('tipe', ['PAID', 'UNPAID'])
            ->latest()
            ->get();

        return view('user.izin.index', compact('data'));
    }

    public function create()
    {
        return view('user.izin.create');
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

        if ($request->hasFile('foto')) {

            $nik = Auth::user()->nik_karyawan;

            // Tentukan folder tujuan di dalam public
            $destinationPath = public_path('izin/' . $nik);

            // Buat folder jika belum ada
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }

            $file = $request->file('foto');

            $filename = time() . '_' . $file->getClientOriginalName();

            $file->move($destinationPath, $filename);

            // Simpan path relatif ke database
            $fotoPath = 'izin/' . $nik . '/' . $filename;
        }

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
            'foto' => $fotoPath ?? '-',
        ]);

        toast()->success('Success', 'Pengajuan izin berhasil dikirim');
        return redirect()->route('izin.index');
    }
}
