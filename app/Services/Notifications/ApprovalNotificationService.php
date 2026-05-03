<?php

namespace App\Services\Notifications;

use App\Models\Cuti;
use App\Models\Employee;
use App\Models\Roster;
use App\Models\RosterOffRequest;
use App\Models\User;
use App\Notifications\StatusPengajuanNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

class ApprovalNotificationService
{
    public function notifyCutiSubmitted(Cuti $cuti): void
    {
        $employee = $cuti->employee;

        if (!$employee) {
            return;
        }

        $this->sendToUsers(
            $this->hodRecipients($employee),
            [
                'judul' => 'Pengajuan Cuti Baru',
                'pesan' => $this->employeeName($employee) . ' mengajukan cuti ' . $this->dateRange($cuti->tanggal_mulai, $cuti->tanggal_berakhir) . '.',
                'url' => route('approval.cuti.hod'),
                'tipe' => 'Approval HOD',
            ]
        );
    }

    public function notifyCutiWaitingForHr(Cuti $cuti): void
    {
        $employee = $cuti->employee;

        if (!$employee) {
            return;
        }

        $this->sendToUsers(
            $this->hrRecipients(),
            [
                'judul' => 'Cuti Menunggu Approval HR',
                'pesan' => 'Pengajuan cuti ' . $this->employeeName($employee) . ' sudah disetujui HOD dan menunggu keputusan HR.',
                'url' => route('approval.cuti.hrd'),
                'tipe' => 'Approval HR',
            ]
        );
    }

    public function notifyIzinSubmitted(Cuti $izin): void
    {
        $employee = $izin->employee;

        if (!$employee) {
            return;
        }

        $izinLabel = $this->izinLabel($izin);

        $this->sendToUsers(
            $this->hodRecipients($employee),
            [
                'judul' => 'Pengajuan ' . $izinLabel . ' Baru',
                'pesan' => $this->employeeName($employee) . ' mengajukan ' . strtolower($izinLabel) . ' ' . $this->dateRange($izin->tanggal_mulai, $izin->tanggal_berakhir) . '.',
                'url' => route('approval.izin.hod'),
                'tipe' => 'Approval HOD',
            ]
        );
    }

    public function notifyIzinWaitingForHr(Cuti $izin): void
    {
        $employee = $izin->employee;

        if (!$employee) {
            return;
        }

        $izinLabel = $this->izinLabel($izin);

        $this->sendToUsers(
            $this->hrRecipients(),
            [
                'judul' => $izinLabel . ' Menunggu Approval HR',
                'pesan' => 'Pengajuan ' . strtolower($izinLabel) . ' ' . $this->employeeName($employee) . ' sudah disetujui HOD dan menunggu keputusan HR.',
                'url' => route('approval.izin.hrd'),
                'tipe' => 'Approval HR',
            ]
        );
    }

    public function notifyRosterSubmitted(Roster $roster): void
    {
        $employee = $roster->employee;

        if (!$employee) {
            return;
        }

        $rosterLabel = $this->rosterLabel($roster);

        $this->sendToUsers(
            $this->hodRecipients($employee),
            [
                'judul' => 'Pengajuan ' . $rosterLabel . ' Baru',
                'pesan' => $this->employeeName($employee) . ' mengajukan ' . strtolower($rosterLabel) . ' periode ' . $this->rosterPeriod($roster) . '.',
                'url' => route('approval.roster.hod'),
                'tipe' => 'Approval HOD',
            ]
        );
    }

    public function notifyRosterWaitingForHr(Roster $roster): void
    {
        $employee = $roster->employee;

        if (!$employee) {
            return;
        }

        $rosterLabel = $this->rosterLabel($roster);

        $this->sendToUsers(
            $this->hrRecipients(),
            [
                'judul' => $rosterLabel . ' Menunggu Approval HR',
                'pesan' => 'Pengajuan ' . strtolower($rosterLabel) . ' ' . $this->employeeName($employee) . ' sudah disetujui HOD dan menunggu keputusan HR.',
                'url' => route('approval.roster.hrd'),
                'tipe' => 'Approval HR',
            ]
        );
    }

    public function notifyRosterOffSubmitted(RosterOffRequest $offRequest): void
    {
        $employee = $offRequest->employee;

        if (!$employee) {
            return;
        }

        $this->sendToUsers(
            $this->hodRecipients($employee),
            [
                'judul' => 'Pengajuan OFF Roster Baru',
                'pesan' => $this->employeeName($employee) . ' mengajukan OFF roster tanggal ' . optional($offRequest->tanggal_off)->format('d M Y') . '.',
                'url' => route('approval.roster-off.hod'),
                'tipe' => 'Approval HOD',
            ]
        );
    }

    public function notifyRosterOffWaitingForHr(RosterOffRequest $offRequest): void
    {
        $employee = $offRequest->employee;

        if (!$employee) {
            return;
        }

        $this->sendToUsers(
            $this->hrRecipients(),
            [
                'judul' => 'OFF Roster Menunggu Approval HR',
                'pesan' => 'Pengajuan OFF roster ' . $this->employeeName($employee) . ' sudah disetujui HOD dan menunggu keputusan HR.',
                'url' => route('approval.roster-off.hrd'),
                'tipe' => 'Approval HR',
            ]
        );
    }

    private function hodRecipients(Employee $employee): Collection
    {
        if (blank($employee->departemen_id)) {
            return collect();
        }

        return $this->activeUsersForRoles(['HOD'])
            ->whereHas('employee', function (Builder $query) use ($employee) {
                $query->where('departemen_id', $employee->departemen_id);
            })
            ->get();
    }

    private function hrRecipients(): Collection
    {
        return $this->activeUsersForRoles(['HR'])
            ->get();
    }

    private function activeUsersForRoles(array $roles): Builder
    {
        if (!Schema::hasTable('roles')) {
            return User::query()->whereRaw('1 = 0');
        }

        $roleNames = collect($roles)
            ->flatMap(function ($role) {
                return array_merge([$role], config('access.roles.' . $role . '.aliases', []));
            })
            ->unique()
            ->values()
            ->all();

        return User::query()
            ->with(['role', 'employee'])
            ->where(function (Builder $query) {
                $query->whereNull('status')
                    ->orWhere('status', 'aktif');
            })
            ->whereHas('role', function (Builder $query) use ($roleNames) {
                $query->whereIn('permission_role', $roleNames);
            });
    }

    private function sendToUsers(Collection $users, array $payload): void
    {
        if ($users->isEmpty()) {
            return;
        }

        try {
            Notification::send($users, new StatusPengajuanNotification($payload));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function employeeName(Employee $employee): string
    {
        return $employee->nama_karyawan ?: $employee->nik;
    }

    private function dateRange($startDate, $endDate): string
    {
        if (!$startDate || !$endDate) {
            return '';
        }

        return date('d M Y', strtotime($startDate)) . ' - ' . date('d M Y', strtotime($endDate));
    }

    private function izinLabel(Cuti $izin): string
    {
        return $izin->tipe === 'PAID' ? 'Izin Berbayar' : 'Izin Tidak Berbayar';
    }

    private function rosterLabel(Roster $roster): string
    {
        return optional($roster->periodeKerjaRoster)->tipe_rencana == 1
            ? 'Cuti Roster'
            : 'Insentif Roster';
    }

    private function rosterPeriod(Roster $roster): string
    {
        $period = $roster->periodeKerjaRoster;

        if (!$period) {
            return date('d M Y', strtotime($roster->tanggal_pengajuan));
        }

        return $this->dateRange($period->periode_awal, $period->periode_akhir);
    }
}
