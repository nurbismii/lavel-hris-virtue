<?php

namespace App\Console\Commands;

use App\Services\Roster\RosterScheduleReminderEligibilityService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendRosterScheduleReminders extends Command
{
    protected $signature = 'roster:send-schedule-reminders
        {--limit=500 : Maksimal jadwal per proses}
        {--dry-run : Tampilkan kandidat tanpa dispatch job}';

    protected $description = 'Mengantrekan email reminder jadwal roster untuk karyawan aktif.';

    public function handle(RosterScheduleReminderEligibilityService $eligibility): int
    {
        $limit = max(1, min(2000, (int) $this->option('limit')));
        $targetDate = Carbon::today()->addDays(max(0, (int) config('roster.reminder_days', 14)));

        if ($this->option('dry-run')) {
            $count = $eligibility->eligibleQuery($targetDate, $targetDate)
                ->limit($limit)
                ->count();
            $this->info($count . ' kandidat reminder untuk ' . $targetDate->toDateString() . '.');
            return self::SUCCESS;
        }

        $queued = $eligibility->dispatchScheduled($targetDate, $limit);

        $this->info($queued . ' reminder roster masuk antrean email.');

        return self::SUCCESS;
    }
}
