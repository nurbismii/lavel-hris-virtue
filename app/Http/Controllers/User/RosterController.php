<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Cuti;
use App\Models\PeriodeKerjaRoster;
use App\Models\Roster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RosterController extends Controller
{
    //
    public function index()
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

        $cutis = Roster::with('employee', 'periodeKerjaRoster')->where('nik_karyawan', Auth::user()->nik_karyawan)->get();

        return view('user.roster.index', compact('cutis'));
    }

    public function create()
    {
        return view('user.roster.create');
    }

    public function store(Request $request)
    {
        DB::beginTransaction();

        $statusPengajuanHod = 0;
        $statusPengajuanHrd = 0;

        try {

            $bulan = bulan_romawi(now()->format('m'));
            $tahun = now()->format('Y');
            $jumlah = Roster::whereYear('created_at', now()->year)->count();
            $jml_cuti = no_urut_surat($jumlah + 1);

            $nomor_surat = '02-' . $jml_cuti . '/BR/HRD-VDNI/' . $bulan . '/' . $tahun;

            $file_name = null;

            if ($request->hasFile('berkas_cuti')) {

                $upload = $request->file('berkas_cuti');
                $file_name = $request->nik_karyawan . '-' . time() . '.' . $upload->getClientOriginalExtension();

                $path = public_path('cuti-roster/' . $request->nik_karyawan);

                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }

                $upload->move($path, $file_name);
            }

            $roster = Roster::create([
                'nomor_surat' => $nomor_surat,
                'nik_karyawan' => $request->nik_karyawan,
                'email' => $request->email,
                'no_telp' => $request->no_telp,
                'tanggal_pengajuan' => now(),

                // CUTI
                'tgl_mulai_cuti' => $request->tgl_mulai_cuti_roster,
                'tgl_mulai_cuti_berakhir' => $request->tgl_berakhir_cuti_roster,

                'tgl_mulai_cuti_tahunan' => $request->tgl_mulai_cuti_tahunan,
                'tgl_mulai_cuti_tahunan_berakhir' => $request->tgl_berakhir_cuti_tahunan,

                'tgl_mulai_off' => $request->tgl_mulai_off,
                'tgl_mulai_off_berakhir' => $request->tgl_berakhir_off,

                // INSENTIF
                'tgl_awal_kerja' => $request->tgl_awal_kerja,
                'tgl_akhir_kerja' => $request->tgl_akhir_kerja,

                // KEBERANGKATAN
                'tgl_keberangkatan' => $request->tanggal_keberangkatan,
                'jam_keberangkatan' => $request->jam_keberangkatan,
                'kota_awal_keberangkatan' => $request->kota_awal_keberangkatan,
                'kota_tujuan_keberangkatan' => $request->kota_tujuan_keberangkatan,
                'catatan_penting_keberangkatan' => $request->catatan_penting_keberangkatan,

                // KEPULANGAN
                'tgl_kepulangan' => $request->tanggal_kepulangan,
                'jam_kepulangan' => $request->jam_kepulangan,
                'kota_awal_kepulangan' => $request->kota_awal_kepulangan,
                'kota_tujuan_kepulangan' => $request->kota_tujuan_kepulangan,
                'catatan_penting_kepulangan' => $request->catatan_penting_kepulangan,

                'file' => $file_name,
                'status_pengajuan' => $statusPengajuanHod, // 0 = Menunggu, 1 = Disetujui, 2 = Ditolak
                'status_pengajuan_hrd' => $statusPengajuanHrd // 0 = Menunggu, 1 = Disetujui, 2 = Ditolak
            ]);

            PeriodeKerjaRoster::create([
                'cuti_roster_id' => $roster->id,
                'periode_awal' => $request->periode_awal,
                'periode_akhir' => $request->periode_akhir,

                'satu' => $request->hari_1,
                'tanggal_satu' => $request->tanggal_1,

                'dua' => $request->hari_2,
                'tanggal_dua' => $request->tanggal_2,

                'tiga' => $request->hari_3,
                'tanggal_tiga' => $request->tanggal_3,

                'empat' => $request->hari_4,
                'tanggal_empat' => $request->tanggal_4,

                'lima' => $request->hari_5,
                'tanggal_lima' => $request->tanggal_5,

                'tipe_rencana' => $request->tipe_rencana,
                'alasan' => $request->alasan,
            ]);


            DB::commit();

            toast()->success('Success', 'Cuti Roster created successfully');
            return back();
        } catch (\Throwable $e) {

            DB::rollBack();

            toast()->error('Error', 'Something wrong' .  $e->getMessage());
            return back();
        }
    }

    public function edit($id)
    {
        $roster = Roster::with('periodeKerjaRoster')->where('id', $id)->first();

        if ($roster->status_pengajuan == '1') {
            toast()->warning('Peringatan', 'Pengajuan roster telah di approve tidak dapat diedit');
            return back();
        }

        return view('user.roster.edit', compact('roster'));
    }

    public function update(Request $request, $id)
    {
        $roster = Roster::with('periodeKerjaRoster')->where('id', $id)->first();

        DB::beginTransaction();

        try {

            $file_name = $roster->file; // default pakai file lama

            if ($request->hasFile('berkas_cuti')) {

                $upload = $request->file('berkas_cuti');
                $file_name = $request->nik_karyawan . '-' . time() . '.' . $upload->getClientOriginalExtension();

                $path = public_path('cuti-roster/' . $request->nik_karyawan);

                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }

                // Hapus file lama jika ada
                if ($roster->file && file_exists($path . '/' . $roster->file)) {
                    unlink($path . '/' . $roster->file);
                }

                $upload->move($path, $file_name);
            }

            $roster->update([

                'nik_karyawan' => $request->nik_karyawan,
                'email' => $request->email,
                'no_telp' => $request->no_telp,

                // CUTI
                'tgl_mulai_cuti' => $request->tgl_mulai_cuti_roster,
                'tgl_mulai_cuti_berakhir' => $request->tgl_berakhir_cuti_roster,

                'tgl_mulai_cuti_tahunan' => $request->tgl_mulai_cuti_tahunan,
                'tgl_mulai_cuti_tahunan_berakhir' => $request->tgl_berakhir_cuti_tahunan,

                'tgl_mulai_off' => $request->tgl_mulai_off,
                'tgl_mulai_off_berakhir' => $request->tgl_berakhir_off,

                // INSENTIF
                'tgl_awal_kerja' => $request->tgl_awal_kerja,
                'tgl_akhir_kerja' => $request->tgl_akhir_kerja,

                // KEBERANGKATAN
                'tgl_keberangkatan' => $request->tanggal_keberangkatan,
                'jam_keberangkatan' => $request->jam_keberangkatan,
                'kota_awal_keberangkatan' => $request->kota_awal_keberangkatan,
                'kota_tujuan_keberangkatan' => $request->kota_tujuan_keberangkatan,
                'catatan_penting_keberangkatan' => $request->catatan_penting_keberangkatan,

                // KEPULANGAN
                'tgl_kepulangan' => $request->tanggal_kepulangan,
                'jam_kepulangan' => $request->jam_kepulangan,
                'kota_awal_kepulangan' => $request->kota_awal_kepulangan,
                'kota_tujuan_kepulangan' => $request->kota_tujuan_kepulangan,
                'catatan_penting_kepulangan' => $request->catatan_penting_kepulangan,

                'file' => $file_name,
            ]);

            PeriodeKerjaRoster::updateOrCreate(
                ['cuti_roster_id' => $roster->id],
                [
                    'periode_awal' => $request->periode_awal,
                    'periode_akhir' => $request->periode_akhir,

                    'satu' => $request->satu,
                    'tanggal_satu' => $request->tanggal_satu,

                    'dua' => $request->dua,
                    'tanggal_dua' => $request->tanggal_dua,

                    'tiga' => $request->tiga,
                    'tanggal_tiga' => $request->tanggal_tiga,

                    'empat' => $request->empat,
                    'tanggal_empat' => $request->tanggal_empat,

                    'lima' => $request->lima,
                    'tanggal_lima' => $request->tanggal_lima,

                    'tipe_rencana' => $request->tipe_rencana,
                    'alasan' => $request->alasan,
                ]
            );

            DB::commit();

            toast()->success('Success', 'Cuti Roster updated successfully');
            return back();
        } catch (\Throwable $e) {

            DB::rollBack();

            toast()->error('Error', 'Something wrong : ' . $e->getMessage());
            return back();
        }
    }

    public function destroy($id)
    {
        $roster = Roster::where('id', $id)->first();

        if ($roster->status_pengajuan == '1') {
            toast()->warning('Peringatan', 'Pengajuan roster telah di approve tidak dapat dihapus');
            return back();
        }

        if ($roster->file) {
            $file_path = public_path('cuti-roster/' . $roster->nik_karyawan . '/' . $roster->file);
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        $roster->delete();
        return back();
    }
}
