<?php

namespace App\Jobs;

use App\Models\RosterSchedule;
use App\Models\User;
use App\Notifications\RosterScheduleReminderNotification;
use App\Services\Roster\RosterScheduleReminderEligibilityService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendRosterScheduleReminder implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const MODE_SCHEDULED = 'scheduled';
    public const MODE_OVERDUE = 'overdue';

    public int $tries = 3;
    public int $timeout = 120;
    public int $uniqueFor = 3600;
    public int $scheduleId;
    public string $mode = self::MODE_SCHEDULED;

    public function __construct(int $scheduleId, string $mode = self::MODE_SCHEDULED)
    {
        $this->scheduleId = $scheduleId;
        $this->mode = $mode;
    }

    public function uniqueId(): string
    {
        return 'roster-schedule-reminder-' . $this->scheduleId;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter(180)];
    }

    public function handle(RosterScheduleReminderEligibilityService $eligibility): void
    {
        $schedule = RosterSchedule::query()->with('employee')->find($this->scheduleId);

        if (!$schedule) {
            return;
        }

        $isEligible = $this->mode === self::MODE_OVERDUE
            ? $eligibility->isOverdueEligible($schedule)
            : $eligibility->isEligible($schedule);

        if (!$isEligible) {
            $this->clearClaim($schedule);
            return;
        }

        $user = User::query()
            ->where('nik_karyawan', $schedule->employee_nik)
            ->whereNotNull('email')
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', 'aktif');
            })
            ->first();

        if (!$user) {
            $this->markDeliveryUnavailable($schedule);
            return;
        }

        $daysBefore = max(0, (int) Carbon::today()->diffInDays($schedule->off_start, false));
        $user->notify(new RosterScheduleReminderNotification($schedule, $daysBefore, $this->mode));

        $schedule->forceFill([
            'reminder_sent_at' => now(),
            'reminder_email' => $user->email,
            'reminder_failed_at' => null,
            'reminder_error' => null,
            'reminder_queued_at' => null,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        RosterSchedule::query()->whereKey($this->scheduleId)->update([
            'reminder_failed_at' => now(),
            'reminder_error' => 'Pengiriman reminder roster gagal. Sistem akan mencoba kembali bila memungkinkan.',
            'reminder_queued_at' => null,
        ]);
    }

    private function clearClaim(RosterSchedule $schedule): void
    {
        $schedule->forceFill([
            'reminder_queued_at' => null,
        ])->save();
    }

    private function markDeliveryUnavailable(RosterSchedule $schedule): void
    {
        $schedule->forceFill([
            'reminder_failed_at' => now(),
            'reminder_error' => 'Akun aktif dengan email tidak tersedia untuk reminder roster.',
            'reminder_queued_at' => null,
        ])->save();
    }
}
