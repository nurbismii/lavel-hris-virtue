<?php

namespace App\Services\Presensi;

use App\Models\Cuti;
use App\Models\Employee;
use App\Models\Presensi;
use App\Models\Roster;
use App\Models\RosterOffRequest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AttendanceStatusService
{
    public const STATUS_CUTI_TAHUNAN = 'Cuti Tahunan';
    public const STATUS_CUTI_ROSTER = 'Cuti Roster';
    public const STATUS_IZIN_BERBAYAR = 'Izin Berbayar';
    public const STATUS_IZIN_TIDAK_BERBAYAR = 'Izin Tidak Berbayar';
    public const STATUS_LIBUR_NASIONAL = 'Libur Nasional';
    public const STATUS_OFF = 'Off';

    public function syncApprovedCuti(Cuti $cuti): void
    {
        $this->refreshCuti($cuti);
    }

    public function syncApprovedIzin(Cuti $cuti): void
    {
        $this->refreshIzin($cuti);
    }

    public function syncApprovedRoster(Roster $roster): void
    {
        $this->refreshRoster($roster);
    }

    public function syncApprovedRosterOff(RosterOffRequest $offRequest): void
    {
        $this->refreshRosterOff($offRequest);
    }

    public function refreshCuti(Cuti $cuti): void
    {
        $this->refreshDateRange($cuti->nik_karyawan, $cuti->tanggal_mulai, $cuti->tanggal_berakhir);
    }

    public function refreshIzin(Cuti $cuti): void
    {
        $this->refreshDateRange($cuti->nik_karyawan, $cuti->tanggal_mulai, $cuti->tanggal_berakhir);
    }

    public function refreshRoster(Roster $roster): void
    {
        $this->refreshDateRange($roster->nik_karyawan, $roster->tgl_mulai_cuti, $roster->tgl_mulai_cuti_berakhir);
        $this->refreshDateRange($roster->nik_karyawan, $roster->tgl_mulai_cuti_tahunan, $roster->tgl_mulai_cuti_tahunan_berakhir);
        $this->refreshDateRange($roster->nik_karyawan, $roster->tgl_mulai_off, $roster->tgl_mulai_off_berakhir);
    }

    public function refreshRosterOff(RosterOffRequest $offRequest): void
    {
        if (!$offRequest->nik_karyawan || !$offRequest->tanggal_off) {
            return;
        }

        $this->syncStatusForDate($offRequest->nik_karyawan, $offRequest->tanggal_off);
    }

    public function syncStatusForDate(string $nikKaryawan, $tanggal): ?string
    {
        if (!$nikKaryawan || !$tanggal) {
            return null;
        }

        $dateString = Carbon::parse($tanggal)->toDateString();

        if (app(AttendancePeriodLockService::class)->lockedPeriodForDate($dateString)) {
            return optional(Presensi::query()
                ->where('nik_karyawan', $nikKaryawan)
                ->whereDate('tanggal', $dateString)
                ->first())->status_presensi;
        }

        $status = $this->resolveStatusForDate($nikKaryawan, $dateString);
        $presensi = Presensi::firstOrNew([
            'nik_karyawan' => $nikKaryawan,
            'tanggal' => $dateString,
        ]);

        if ($status) {
            $presensi->jam_masuk = null;
            $presensi->jam_istirahat = null;
            $presensi->jam_kembali_istirahat = null;
            $presensi->jam_pulang = null;
            $presensi->status_presensi = $status;
            $presensi->save();

            return $status;
        }

        if ($presensi->exists && $presensi->status_presensi) {
            $presensi->status_presensi = null;
            $presensi->save();
        }

        return null;
    }

    public function resolveStatusForDate(string $nikKaryawan, $tanggal): ?string
    {
        $dateString = Carbon::parse($tanggal)->toDateString();

        $rosterOffStatus = $this->resolveRosterOffStatusForDate($nikKaryawan, $dateString);

        if ($rosterOffStatus) {
            return $rosterOffStatus;
        }

        $rosterStatus = $this->resolveRosterStatusForDate($nikKaryawan, $dateString);

        if ($rosterStatus) {
            return $rosterStatus;
        }

        $cuti = Cuti::query()
            ->where('nik_karyawan', $nikKaryawan)
            ->where('status_hod', 1)
            ->where('status_hrd', 1)
            ->whereDate('tanggal_mulai', '<=', $dateString)
            ->whereDate('tanggal_berakhir', '>=', $dateString)
            ->latest('id')
            ->first();

        if ($cuti) {
            if ($cuti->tipe === 'CUTI') {
                return self::STATUS_CUTI_TAHUNAN;
            }

            if ($cuti->tipe === 'PAID') {
                return self::STATUS_IZIN_BERBAYAR;
            }

            if ($cuti->tipe === 'UNPAID') {
                return self::STATUS_IZIN_TIDAK_BERBAYAR;
            }
        }

        $acceptedOvertime = app(OvertimeOrderService::class)->getAcceptedOrderForDate($nikKaryawan, $dateString);

        if ($acceptedOvertime) {
            return null;
        }

        $employee = Employee::query()
            ->with('workPattern')
            ->where('nik', $nikKaryawan)
            ->first();
        $workScheduleService = app(WorkScheduleService::class);

        if ($employee && $workScheduleService->resolveFinalStatus($employee, $dateString) === WorkScheduleService::STATUS_OFF) {
            return $workScheduleService->isNationalHolidayDate($dateString)
                ? self::STATUS_LIBUR_NASIONAL
                : self::STATUS_OFF;
        }

        return null;
    }

    private function refreshDateRange(string $nikKaryawan, ?string $tanggalMulai, ?string $tanggalBerakhir): void
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
            $this->syncStatusForDate($nikKaryawan, $tanggal);
        }
    }

    private function resolveRosterStatusForDate(string $nikKaryawan, string $tanggal): ?string
    {
        $roster = Roster::query()
            ->select([
                'cuti_roster.tgl_mulai_cuti',
                'cuti_roster.tgl_mulai_cuti_berakhir',
                'cuti_roster.tgl_mulai_cuti_tahunan',
                'cuti_roster.tgl_mulai_cuti_tahunan_berakhir',
                'cuti_roster.tgl_mulai_off',
                'cuti_roster.tgl_mulai_off_berakhir',
            ])
            ->join('periode_kerja_roster', 'periode_kerja_roster.cuti_roster_id', '=', 'cuti_roster.id')
            ->where('cuti_roster.nik_karyawan', $nikKaryawan)
            ->where('cuti_roster.status_pengajuan', 1)
            ->where('cuti_roster.status_pengajuan_hrd', 1)
            ->where('periode_kerja_roster.tipe_rencana', 1)
            ->where(function ($query) use ($tanggal) {
                $query->where(function ($range) use ($tanggal) {
                    $range->whereNotNull('cuti_roster.tgl_mulai_cuti')
                        ->whereNotNull('cuti_roster.tgl_mulai_cuti_berakhir')
                        ->whereDate('cuti_roster.tgl_mulai_cuti', '<=', $tanggal)
                        ->whereDate('cuti_roster.tgl_mulai_cuti_berakhir', '>=', $tanggal);
                })->orWhere(function ($range) use ($tanggal) {
                    $range->whereNotNull('cuti_roster.tgl_mulai_cuti_tahunan')
                        ->whereNotNull('cuti_roster.tgl_mulai_cuti_tahunan_berakhir')
                        ->whereDate('cuti_roster.tgl_mulai_cuti_tahunan', '<=', $tanggal)
                        ->whereDate('cuti_roster.tgl_mulai_cuti_tahunan_berakhir', '>=', $tanggal);
                })->orWhere(function ($range) use ($tanggal) {
                    $range->whereNotNull('cuti_roster.tgl_mulai_off')
                        ->whereNotNull('cuti_roster.tgl_mulai_off_berakhir')
                        ->whereDate('cuti_roster.tgl_mulai_off', '<=', $tanggal)
                        ->whereDate('cuti_roster.tgl_mulai_off_berakhir', '>=', $tanggal);
                });
            })
            ->latest('cuti_roster.id')
            ->first();

        if (!$roster) {
            return null;
        }

        if ($this->dateWithinRange($roster->tgl_mulai_cuti, $roster->tgl_mulai_cuti_berakhir, $tanggal)) {
            return self::STATUS_CUTI_ROSTER;
        }

        if ($this->dateWithinRange($roster->tgl_mulai_cuti_tahunan, $roster->tgl_mulai_cuti_tahunan_berakhir, $tanggal)) {
            return self::STATUS_CUTI_TAHUNAN;
        }

        if ($this->dateWithinRange($roster->tgl_mulai_off, $roster->tgl_mulai_off_berakhir, $tanggal)) {
            return self::STATUS_OFF;
        }

        return null;
    }

    private function resolveRosterOffStatusForDate(string $nikKaryawan, string $tanggal): ?string
    {
        $exists = RosterOffRequest::query()
            ->effectiveForAttendance()
            ->where('nik_karyawan', $nikKaryawan)
            ->whereDate('tanggal_off', $tanggal)
            ->exists();

        return $exists ? self::STATUS_OFF : null;
    }

    private function dateWithinRange(?string $tanggalMulai, ?string $tanggalBerakhir, string $tanggal): bool
    {
        if (!$tanggalMulai || !$tanggalBerakhir) {
            return false;
        }

        $start = Carbon::parse($tanggalMulai)->toDateString();
        $end = Carbon::parse($tanggalBerakhir)->toDateString();

        return $tanggal >= $start && $tanggal <= $end;
    }
}
