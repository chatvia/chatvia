<?php

declare(strict_types=1);

namespace App\Admin\Status;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * When the scheduler last woke up. `schedule:work` (or cron) starts
 * `schedule:run` at the top of every minute, due tasks or not, so this tells
 * the Status page within a minute that the scheduler runs, instead of waiting
 * for the next task that happens to be due.
 */
final class SchedulerHeartbeat
{
    private const string KEY = 'chatvia:scheduler:last_run_at';

    public static function record(): void
    {
        Cache::forever(self::KEY, now()->getTimestamp());
    }

    public static function lastRunAt(): ?Carbon
    {
        $timestamp = Cache::get(self::KEY);

        return is_numeric($timestamp) ? Carbon::createFromTimestamp((int) $timestamp) : null;
    }
}
