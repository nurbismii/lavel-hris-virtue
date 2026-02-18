<?php

namespace App\Services\Cuti;

use App\Models\Cuti;
use App\Models\User;
use Carbon\Carbon;

class CutiService
{
    public function storeCuti($request)
    {
        $STATUS_PEMOHON = 1;
        $STATUS_HOD = 0;
        $STATUS_HR = 0;

        $jumlahHari = Carbon::parse($request->tanggal_mulai)
            ->diffInDays(Carbon::parse($request->tanggal_berakhir)) + 1;

        $existing = Cuti::where('nik_karyawan', $request->nik_karyawan)
            ->whereDate('tanggal', now())
            ->where('status_hod', 0)
            ->exists();

        if ($existing) {
            return [
                'status' => false,
                'message' => 'Pengajuan kamu sudah tersedia dan masih menunggu persetujuan'
            ];
        }

        if ($jumlahHari > $request->sisa_cuti) {
            return [
                'status' => false,
                'message' => 'Sisa cuti tidak cukup'
            ];
        }

        Cuti::create([
            'nik_karyawan' => $request->nik_karyawan,
            'tanggal' => $request->tanggal,
            'tanggal_mulai' => $request->tanggal_mulai,
            'tanggal_berakhir' => $request->tanggal_berakhir,
            'jumlah' => $jumlahHari,
            'status_pemohon' => $STATUS_PEMOHON,
            'status_hod' => $STATUS_HOD,
            'status_hrd' => $STATUS_HR,
            'tipe' => 'CUTI',
            'keterangan' => $request->keterangan,
            'created_at' => now()
        ]);

        return [
            'status' => true,
            'message' => 'Pengajuan cuti berhasil dibuat'
        ];
    }

    public function updateCuti($request, $id)
    {

        $jumlahHari = Carbon::parse($request->tanggal_mulai)
            ->diffInDays(Carbon::parse($request->tanggal_berakhir)) + 1;

        if ($jumlahHari > $request->sisa_cuti) {
            return [
                'status' => false,
                'message' => 'Sisa cuti tidak cukup'
            ];
        }

        Cuti::where('id', $id)->update([
            'tanggal_mulai' => $request->tanggal_mulai,
            'tanggal_berakhir' => $request->tanggal_berakhir,
            'jumlah' => $jumlahHari,
            'tipe' => 'CUTI',
            'keterangan' => $request->keterangan,
            'updated_at' => now()
        ]);

        return [
            'status' => true,
            'message' => 'Pengajuan cuti berhasil diperbarui'
        ];
    }
}
