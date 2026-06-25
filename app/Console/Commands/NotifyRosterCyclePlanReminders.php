<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\StatusPengajuanNotification;
use App\Services\Presensi\RosterCyclePlanService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class NotifyRosterCyclePlanReminders extends Command
{
    protected $signature = 'roster:notify-cycle-plan-reminders
        {--days=3 : H-n sebelum masa kerja siklus roster berakhir}
        {--limit=500 : Maksimal user yang diproses per run}
        {--dry-run : Cek kandidat tanpa mengirim notifikasi}';

    protected $description = 'Mengirim notifikasi H-3 agar karyawan pola siklus roster mingguan mengajukan Insentif Roster atau Cuti Roster.';

    public function handle(RosterCyclePlanService $rosterCyclePlanService): int
    {
        $days = max(1, min((int) $this->option('days'), 30));
        $limit = max(1, min((int) $this->option('limit'), 2000));
        $dryRun = (bool) $this->option('dry-run');
        $today = Carbon::today();
        $users = $this->dueUsers($limit)->get();

        if ($users->isEmpty()) {
            $this->info('Tidak ada user pola siklus roster mingguan yang perlu dicek.');
            return self::SUCCESS;
        }

        $sent = 0;
        $skippedNotDue = 0;
        $skippedExistingPlan = 0;
        $skippedDuplicate = 0;

        foreach ($users as $user) {
            $employee = $user->employee;

            if (!$employee) {
                continue;
            }

            $cycle = $rosterCyclePlanService->reminderCycleFor($employee, $days, $today);

            if (!$cycle) {
                $skippedNotDue++;
                continue;
            }

            if ($rosterCyclePlanService->hasActiveRosterPlanForCycle(
                $employee->nik,
                $cycle['off_start'],
                $cycle['off_end']
            )) {
                $skippedExistingPlan++;
                continue;
            }

            $key = $this->notificationKey($employee->nik, $cycle['off_start'], $cycle['off_end'], $days);

            if ($this->alreadyNotified($user, $key)) {
                $skippedDuplicate++;
                continue;
            }

            $patternLabel = $this->patternLabel($cycle);
            $payload = [
                'judul' => 'Pengajuan Roster Perlu Dipilih',
                'pesan' => sprintf(
                    'Masa kerja pola %s Anda akan berakhir pada %s. Ajukan Cuti Roster jika mengambil cuti roster %s - %s, atau Insentif Roster jika tetap bekerja.',
                    $patternLabel,
                    $this->formatDate($cycle['work_end']),
                    $this->formatDate($cycle['off_start']),
                    $this->formatDate($cycle['off_end'])
                ),
                'url' => route('roster.create'),
                'tipe' => 'Reminder Roster',
                'key' => $key,
                'metadata' => [
                    'employee_nik' => $employee->nik,
                    'reminder_day' => $days,
                    'work_end' => $cycle['work_end']->toDateString(),
                    'off_start' => $cycle['off_start']->toDateString(),
                    'off_end' => $cycle['off_end']->toDateString(),
                    'work_weeks' => $cycle['work_weeks'],
                    'off_weeks' => $cycle['off_weeks'],
                    'pattern_code' => $cycle['pattern_code'],
                ],
            ];

            if (!$dryRun) {
                $user->notify(new StatusPengajuanNotification($payload));
            }

            $sent++;
        }

        $this->info(sprintf(
            'Reminder siklus roster: %d %s, %d belum jatuh tempo, %d sudah ada pengajuan aktif, %d duplikat.',
            $sent,
            $dryRun ? 'kandidat' : 'terkirim',
            $skippedNotDue,
            $skippedExistingPlan,
            $skippedDuplicate
        ));

        return self::SUCCESS;
    }

    private function dueUsers(int $limit): Builder
    {
        return User::query()
            ->with(['employee.workPattern'])
            ->whereNotNull('nik_karyawan')
            ->where(function (Builder $query) {
                $query->whereNull('status')
                    ->orWhere('status', 'aktif');
            })
            ->whereHas('employee', function (Builder $employeeQuery) {
                $employeeQuery
                    ->where('status_resign', 'AKTIF')
                    ->whereNotNull('work_pattern_start_date')
                    ->whereHas('workPattern', function (Builder $patternQuery) {
                        $patternQuery
                            ->where(function (Builder $basisQuery) {
                                $basisQuery->whereNull('pattern_basis')
                                    ->orWhere('pattern_basis', 'cycle');
                            })
                            ->where('work_duration_value', '>', 0)
                            ->where('work_duration_unit', 'week')
                            ->where('off_duration_value', '>', 0)
                            ->where('off_duration_unit', 'week');
                    });
            })
            ->orderBy('nik_karyawan')
            ->limit($limit);
    }

    private function alreadyNotified(User $user, string $key): bool
    {
        return $user->notifications()
            ->where('type', StatusPengajuanNotification::class)
            ->where('data', 'like', '%"key":"' . addcslashes($key, '\\%_') . '"%')
            ->exists();
    }

    private function notificationKey(string $nik, Carbon $offStart, Carbon $offEnd, int $days): string
    {
        return sprintf(
            'roster_cycle_plan:%s:%s:%s:h-%d',
            $nik,
            $offStart->toDateString(),
            $offEnd->toDateString(),
            $days
        );
    }

    private function patternLabel(array $cycle): string
    {
        if (!empty($cycle['pattern_code'])) {
            return (string) $cycle['pattern_code'];
        }

        return sprintf('%d:%d', $cycle['work_weeks'], $cycle['off_weeks']);
    }

    private function formatDate(Carbon $date): string
    {
        return $date->translatedFormat('d M Y');
    }
}
