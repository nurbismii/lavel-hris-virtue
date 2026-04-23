<?php

namespace App\Services\Presensi;

use App\Models\Cuti;
use App\Models\Presensi;
use App\Models\Roster;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AttendanceStatusService
{
    public const STATUS_CUTI_TAHUNAN = 'Cuti Tahunan';
    public const STATUS_CUTI_ROSTER = 'Cuti Roster';
    public const STATUS_IZIN_BERBAYAR = 'Izin Berbayar';
    public const STATUS_IZIN_TIDAK_BERBAYAR = 'Izin Tidak Berbayar';

    public function syncApprovedCuti(Cuti $cuti): void
    {
        $this->syncDateRange(
            $cuti->nik_karyawan,
            $cuti->tanggal_mulai,
            $cuti->tanggal_berakhir,
            self::STATUS_CUTI_TAHUNAN
        );
    }

    public function syncApprovedIzin(Cuti $cuti): void
    {
        $status = $cuti->tipe === 'PAID'
            ? self::STATUS_IZIN_BERBAYAR
            : self::STATUS_IZIN_TIDAK_BERBAYAR;

        $this->syncDateRange(
            $cuti->nik_karyawan,
            $cuti->tanggal_mulai,
            $cuti->tanggal_berakhir,
            $status
        );
    }

    public function syncApprovedRoster(Roster $roster): void
    {
        $periode = $roster->periodeKerjaRoster;

        if (!$periode || (int) $periode->tipe_rencana !== 1) {
            return;
        }

        $this->syncDateRange(
            $roster->nik_karyawan,
            $roster->tgl_mulai_cuti,
            $roster->tgl_mulai_cuti_berakhir,
            self::STATUS_CUTI_ROSTER
        );

        $this->syncDateRange(
            $roster->nik_karyawan,
            $roster->tgl_mulai_cuti_tahunan,
            $roster->tgl_mulai_cuti_tahunan_berakhir,
            self::STATUS_CUTI_TAHUNAN
        );
    }

    public function syncDateRange(string $nikKaryawan, ?string $tanggalMulai, ?string $tanggalBerakhir, string $status): void
    {
        if (!$nikKaryawan || !$tanggalMulai || !$tanggalBerakhir) {
            return;
        }

        $start = Carbon::parse($tanggalMulai)->startOfDay();
        $end = Carbon::parse($tanggalBerakhir)->startOfDay();

        if ($end->lt($start)) {
            return;
        }

        foreach (CarbonPeriod::create($start, $end) as $tanggal) {
            Presensi::updateOrCreate(
                [
                    'nik_karyawan' => $nikKaryawan,
                    'tanggal' => $tanggal->format('Y-m-d'),
                ],
                [
                    'jam_masuk' => null,
                    'jam_istirahat' => null,
                    'jam_kembali_istirahat' => null,
                    'jam_pulang' => null,
                    'status_presensi' => $status,
                ]
            );
        }
    }
}
