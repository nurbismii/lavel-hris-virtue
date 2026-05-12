<?php

namespace App\Services\Notifications;

use App\Models\ApprovalDelegation;
use App\Models\ApprovalDelegationAssignment;
use App\Models\AttendanceCorrection;
use App\Models\Cuti;
use App\Models\Employee;
use App\Models\Roster;
use App\Models\RosterOffRequest;
use App\Models\User;
use App\Notifications\StatusPengajuanNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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

        $payload = [
            'judul' => 'Pengajuan Cuti Baru',
            'pesan' => $this->employeeName($employee) . ' mengajukan cuti ' . $this->dateRange($cuti->tanggal_mulai, $cuti->tanggal_berakhir) . '.',
            'url' => route('approval.cuti.hod'),
            'tipe' => 'Approval HOD',
        ];

        if ($this->notifyDelegatesIfNeeded($cuti, ApprovalDelegation::MODULE_CUTI, $payload)) {
            return;
        }

        $this->sendToUsers($this->hodRecipients($employee), $payload);
    }

    public function notifyCutiWaitingForHod(Cuti $cuti): void
    {
        $employee = $cuti->employee;

        if (!$employee) {
            return;
        }

        $this->sendToUsers(
            $this->hodRecipients($employee),
            [
                'judul' => 'Cuti Menunggu Approval HOD',
                'pesan' => 'Pengajuan cuti ' . $this->employeeName($employee) . ' sudah diverifikasi delegasi dan menunggu keputusan HOD.',
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

        $payload = [
            'judul' => 'Pengajuan ' . $izinLabel . ' Baru',
            'pesan' => $this->employeeName($employee) . ' mengajukan ' . strtolower($izinLabel) . ' ' . $this->dateRange($izin->tanggal_mulai, $izin->tanggal_berakhir) . '.',
            'url' => route('approval.izin.hod'),
            'tipe' => 'Approval HOD',
        ];

        if ($this->notifyDelegatesIfNeeded($izin, ApprovalDelegation::MODULE_IZIN, $payload)) {
            return;
        }

        $this->sendToUsers($this->hodRecipients($employee), $payload);
    }

    public function notifyIzinWaitingForHod(Cuti $izin): void
    {
        $employee = $izin->employee;

        if (!$employee) {
            return;
        }

        $izinLabel = $this->izinLabel($izin);

        $this->sendToUsers(
            $this->hodRecipients($employee),
            [
                'judul' => $izinLabel . ' Menunggu Approval HOD',
                'pesan' => 'Pengajuan ' . strtolower($izinLabel) . ' ' . $this->employeeName($employee) . ' sudah diverifikasi delegasi dan menunggu keputusan HOD.',
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

        $payload = [
            'judul' => 'Pengajuan ' . $rosterLabel . ' Baru',
            'pesan' => $this->employeeName($employee) . ' mengajukan ' . strtolower($rosterLabel) . ' periode ' . $this->rosterPeriod($roster) . '.',
            'url' => route('approval.roster.hod'),
            'tipe' => 'Approval HOD',
        ];

        if ($this->notifyDelegatesIfNeeded($roster, ApprovalDelegation::MODULE_ROSTER, $payload)) {
            return;
        }

        $this->sendToUsers($this->hodRecipients($employee), $payload);
    }

    public function notifyRosterWaitingForHod(Roster $roster): void
    {
        $employee = $roster->employee;

        if (!$employee) {
            return;
        }

        $rosterLabel = $this->rosterLabel($roster);

        $this->sendToUsers(
            $this->hodRecipients($employee),
            [
                'judul' => $rosterLabel . ' Menunggu Approval HOD',
                'pesan' => 'Pengajuan ' . strtolower($rosterLabel) . ' ' . $this->employeeName($employee) . ' sudah diverifikasi delegasi dan menunggu keputusan HOD.',
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

        $payload = [
            'judul' => 'Pengajuan OFF Roster Baru',
            'pesan' => $this->employeeName($employee) . ' mengajukan OFF roster tanggal ' . optional($offRequest->tanggal_off)->format('d M Y') . '.',
            'url' => route('approval.roster-off.hod'),
            'tipe' => 'Approval HOD',
        ];

        if ($this->notifyDelegatesIfNeeded($offRequest, ApprovalDelegation::MODULE_ROSTER_OFF, $payload)) {
            return;
        }

        $this->sendToUsers($this->hodRecipients($employee), $payload);
    }

    public function notifyRosterOffWaitingForHod(RosterOffRequest $offRequest): void
    {
        $employee = $offRequest->employee;

        if (!$employee) {
            return;
        }

        $this->sendToUsers(
            $this->hodRecipients($employee),
            [
                'judul' => 'OFF Roster Menunggu Approval HOD',
                'pesan' => 'Pengajuan OFF roster ' . $this->employeeName($employee) . ' sudah diverifikasi delegasi dan menunggu keputusan HOD.',
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

    public function notifyAttendanceCorrectionSubmitted(AttendanceCorrection $correction): void
    {
        $employee = $correction->employee;

        if (!$employee) {
            return;
        }

        $payload = [
            'judul' => 'Pengajuan Presensi Baru',
            'pesan' => $this->employeeName($employee) . ' mengajukan koreksi/izin presensi tanggal ' . optional($correction->tanggal)->format('d M Y') . '.',
            'url' => route('approval.attendance-corrections.hod'),
            'tipe' => 'Approval HOD',
        ];

        if ($this->notifyDelegatesIfNeeded($correction, ApprovalDelegation::MODULE_ATTENDANCE_CORRECTION, $payload)) {
            return;
        }

        $this->sendToUsers($this->hodRecipients($employee), $payload);
    }

    public function notifyAttendanceCorrectionWaitingForHod(AttendanceCorrection $correction): void
    {
        $employee = $correction->employee;

        if (!$employee) {
            return;
        }

        $this->sendToUsers(
            $this->hodRecipients($employee),
            [
                'judul' => 'Pengajuan Presensi Menunggu Approval HOD',
                'pesan' => 'Pengajuan presensi ' . $this->employeeName($employee) . ' sudah diverifikasi delegasi dan menunggu keputusan HOD.',
                'url' => route('approval.attendance-corrections.hod'),
                'tipe' => 'Approval HOD',
            ]
        );
    }

    public function notifyDelegatedRequestWaitingForHod(Model $model, string $module): void
    {
        switch ($module) {
            case ApprovalDelegation::MODULE_CUTI:
                if ($model instanceof Cuti) {
                    $this->notifyCutiWaitingForHod($model);
                }
                return;

            case ApprovalDelegation::MODULE_IZIN:
                if ($model instanceof Cuti) {
                    $this->notifyIzinWaitingForHod($model);
                }
                return;

            case ApprovalDelegation::MODULE_ROSTER:
                if ($model instanceof Roster) {
                    $this->notifyRosterWaitingForHod($model);
                }
                return;

            case ApprovalDelegation::MODULE_ROSTER_OFF:
                if ($model instanceof RosterOffRequest) {
                    $this->notifyRosterOffWaitingForHod($model);
                }
                return;

            case ApprovalDelegation::MODULE_ATTENDANCE_CORRECTION:
                if ($model instanceof AttendanceCorrection) {
                    $this->notifyAttendanceCorrectionWaitingForHod($model);
                }
                return;
        }
    }

    private function hodRecipients(Employee $employee): Collection
    {
        if (blank($employee->departemen_id) && blank($employee->divisi_id)) {
            return collect();
        }

        return $this->activeUsersForRoles(['HOD'])
            ->get()
            ->filter(function (User $hod) use ($employee) {
                $departmentAllowed = filled($employee->departemen_id)
                    && in_array((string) $employee->departemen_id, $hod->scopedDepartmentIds(), true);
                $divisionAllowed = filled($employee->divisi_id)
                    && in_array((string) $employee->divisi_id, $hod->scopedDivisionIds(), true);

                return $departmentAllowed || $divisionAllowed;
            })
            ->values();
    }

    private function hrRecipients(): Collection
    {
        return $this->activeUsersForRoles(['HR'])
            ->get();
    }

    private function notifyDelegatesIfNeeded(Model $model, string $module, array $hodPayload): bool
    {
        if ($model->getAttribute('delegate_status') === null
            || (int) $model->getAttribute('delegate_status') !== 0) {
            return false;
        }

        $delegates = $this->delegateRecipients($model, $module);

        if ($delegates->isEmpty()) {
            return false;
        }

        $payload = array_merge($hodPayload, [
            'judul' => str_replace(' Baru', ' Menunggu Verifikasi Delegasi', $hodPayload['judul']),
            'url' => route('approval.delegate.index', ['module' => str_replace('_', '-', $module)]),
            'tipe' => 'Approval Delegasi',
        ]);

        $this->sendToUsers($delegates, $payload);

        return true;
    }

    private function delegateRecipients(Model $model, string $module): Collection
    {
        if (!Schema::hasTable('approval_delegation_request_assignments')) {
            return collect();
        }

        $delegateIds = ApprovalDelegationAssignment::query()
            ->where('approvable_type', get_class($model))
            ->where('approvable_id', (string) $model->getKey())
            ->where('module', $module)
            ->pluck('delegate_user_id')
            ->unique()
            ->values();

        if ($delegateIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->with('employee')
            ->whereIn('id', $delegateIds)
            ->where(function (Builder $query) {
                $query->whereNull('status')
                    ->orWhere('status', 'aktif');
            })
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
            ->with(array_filter(['role', Schema::hasTable('role_user') ? 'additionalRoles' : null, 'employee']))
            ->where(function (Builder $query) {
                $query->whereNull('status')
                    ->orWhere('status', 'aktif');
            })
            ->where(function (Builder $query) use ($roleNames) {
                $query->whereHas('role', function (Builder $roleQuery) use ($roleNames) {
                    $roleQuery->whereIn('permission_role', $roleNames);
                });

                if (Schema::hasTable('role_user')) {
                    $query->orWhereHas('additionalRoles', function (Builder $roleQuery) use ($roleNames) {
                        $roleQuery->whereIn('permission_role', $roleNames);
                    });
                }
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
