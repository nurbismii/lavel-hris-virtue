<?php

namespace App\Services\Roster;

use App\Support\SafeExceptionLogger;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Throwable;

class RosterScheduleActionMutex
{
    private $cache;
    private $logger;

    public function __construct(CacheRepository $cache, SafeExceptionLogger $logger)
    {
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public function acquire(int $scheduleId, ?int $waitMilliseconds = null): ?Lock
    {
        $ttlSeconds = max(1, (int) config('roster.action_mutex_ttl_seconds', 180));
        $waitMilliseconds = $waitMilliseconds === null
            ? max(0, (int) config('roster.action_mutex_wait_milliseconds', 1000))
            : max(0, $waitMilliseconds);
        $retryMilliseconds = max(1, min(
            250,
            (int) config('roster.action_mutex_retry_milliseconds', 50)
        ));

        try {
            $lock = $this->cache->lock($this->key($scheduleId), $ttlSeconds);
        } catch (Throwable $exception) {
            $this->logger->warning('roster_schedule.action_mutex.create', $exception);

            return null;
        }

        $deadline = microtime(true) + ($waitMilliseconds / 1000);

        do {
            try {
                if ($lock->get()) {
                    return $lock;
                }
            } catch (Throwable $exception) {
                $this->logger->warning('roster_schedule.action_mutex.acquire', $exception);

                return null;
            }

            $remainingMilliseconds = (int) floor(($deadline - microtime(true)) * 1000);
            if ($remainingMilliseconds <= 0) {
                break;
            }

            usleep(min($retryMilliseconds, $remainingMilliseconds) * 1000);
        } while (microtime(true) < $deadline);

        return null;
    }

    public function release(Lock $lock): void
    {
        try {
            $lock->release();
        } catch (Throwable $exception) {
            $this->logger->warning('roster_schedule.action_mutex.release', $exception);
        }
    }

    public function key(int $scheduleId): string
    {
        return 'roster_schedule:action:' . $scheduleId;
    }
}
