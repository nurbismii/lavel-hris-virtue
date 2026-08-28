<?php

namespace App\Services\Roster;

use App\Jobs\SendRosterScheduleReminder;
use App\Models\RosterSchedule;
use Carbon\Carbon;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Builder;

class RosterScheduleReminderEligibilityService
{
    private const CLAIM_CHUNK_SIZE = 200;

    public function eligibleQuery(Carbon $from, Carbon $to): Builder
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();
        $today = Carbon::today();

        if ($end->lt($today) || $end->lt($start)) {
            return RosterSchedule::query()->whereRaw('1 = 0');
        }

        if ($start->lt($today)) {
            $start = $today;
        }

        return $this->baseEligibleQuery()
            ->where('off_start', '>=', $start->toDateString())
            ->where('off_start', '<', $end->copy()->addDay()->toDateString());
    }

    public function isEligible(RosterSchedule $schedule, ?Carbon $today = null): bool
    {
        $today = ($today ?: Carbon::today())->copy()->startOfDay();

        return $this->baseEligibleQuery()
            ->whereKey($schedule->id)
            ->where('off_start', '>=', $today->toDateString())
            ->exists();
    }

    public function isOverdueEligible(RosterSchedule $schedule, ?Carbon $today = null): bool
    {
        $today = ($today ?: Carbon::today())->copy()->startOfDay();

        return $this->overdueEligibleQuery($today)
            ->whereKey($schedule->id)
            ->exists();
    }

    public function dispatchOverdue(RosterSchedule $schedule): bool
    {
        $claimedAt = now();
        $claimed = $this->overdueEligibleQuery(Carbon::today())
            ->whereKey($schedule->id)
            ->whereNull('reminder_queued_at')
            ->update(['reminder_queued_at' => $claimedAt]) === 1;

        if (!$claimed) {
            return false;
        }

        $job = new SendRosterScheduleReminder($schedule->id, SendRosterScheduleReminder::MODE_OVERDUE);

        try {
            dispatch($job);

            return true;
        } catch (\Throwable $exception) {
            RosterSchedule::query()
                ->whereKey($schedule->id)
                ->where('reminder_queued_at', $claimedAt)
                ->update(['reminder_queued_at' => null]);

            try {
                (new UniqueLock(app(Cache::class)))->release($job);
            } catch (\Throwable $releaseException) {
                report($releaseException);
            }

            report($exception);

            return false;
        }
    }

    public function dispatchLate(array $scheduleIds, Carbon $from, Carbon $to): int
    {
        $ids = collect($scheduleIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return 0;
        }

        $queued = 0;
        foreach (array_chunk($ids, self::CLAIM_CHUNK_SIZE) as $chunk) {
            $schedules = $this->eligibleQuery($from, $to)
                ->whereKey($chunk)
                ->orderBy('id')
                ->get();

            foreach ($schedules as $schedule) {
                if ($this->claim($schedule->id, $from, $to)) {
                    if ($this->dispatch($schedule->id, $queued)) {
                        $queued++;
                    }
                }
            }
        }

        return $queued;
    }

    public function dispatchScheduled(Carbon $targetDate, int $limit = 500): int
    {
        $limit = max(1, min(2000, $limit));
        $queued = 0;
        $schedules = $this->eligibleQuery($targetDate, $targetDate)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($schedules as $schedule) {
            if ($this->claim($schedule->id, $targetDate, $targetDate)) {
                if ($this->dispatch($schedule->id, $queued)) {
                    $queued++;
                }
            }
        }

        return $queued;
    }

    private function baseEligibleQuery(): Builder
    {
        return RosterSchedule::query()
            ->active()
            ->where('realization_type', RosterSchedule::REALIZATION_PENDING)
            ->whereNull('reminder_sent_at')
            ->whereHas('employee', function (Builder $query): void {
                $query->where('status_resign', 'AKTIF');
            })
            ->whereDoesntHave('applications', function (Builder $query): void {
                $this->applyActiveApplicationFilter($query);
            });
    }

    private function overdueEligibleQuery(Carbon $today): Builder
    {
        $cooldownHours = max(1, (int) config('roster.overdue_reminder_cooldown_hours', 24));
        $cooldownEndsBefore = now()->subHours($cooldownHours);

        return RosterSchedule::query()
            ->active()
            ->where('realization_type', RosterSchedule::REALIZATION_PENDING)
            ->whereDate('off_start', '<', $today->toDateString())
            ->whereHas('employee', function (Builder $query): void {
                $query->where('status_resign', 'AKTIF');
            })
            ->whereDoesntHave('applications', function (Builder $query): void {
                $this->applyActiveApplicationFilter($query);
            })
            ->where(function (Builder $query) use ($cooldownEndsBefore): void {
                $query->whereNull('reminder_sent_at')
                    ->orWhere('reminder_sent_at', '<=', $cooldownEndsBefore);
            });
    }

    private function applyActiveApplicationFilter(Builder $query): void
    {
        $query->where(function (Builder $status): void {
            $status->where(function (Builder $hod): void {
                $hod->whereNull('status_pengajuan')
                    ->orWhere('status_pengajuan', '!=', 2);
            })->where(function (Builder $hrd): void {
                $hrd->whereNull('status_pengajuan_hrd')
                    ->orWhere('status_pengajuan_hrd', '!=', 2);
            });
        });
    }

    public function claim(int $scheduleId, Carbon $from, Carbon $to): bool
    {
        return $this->eligibleQuery($from, $to)
            ->whereKey($scheduleId)
            ->whereNull('reminder_queued_at')
            ->update(['reminder_queued_at' => now()]) === 1;
    }

    private function dispatch(int $scheduleId, int $position): bool
    {
        try {
            $delay = max(0, min(30, (int) config('roster.reminder_delay_seconds', 2)));
            SendRosterScheduleReminder::dispatch($scheduleId)
                ->delay(now()->addSeconds($position * $delay));
            return true;
        } catch (\Throwable $exception) {
            RosterSchedule::query()
                ->whereKey($scheduleId)
                ->whereNull('reminder_sent_at')
                ->update(['reminder_queued_at' => null]);

            return false;
        }
    }
}
