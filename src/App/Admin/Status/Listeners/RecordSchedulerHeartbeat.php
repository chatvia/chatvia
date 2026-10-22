<?php

declare(strict_types=1);

namespace App\Admin\Status\Listeners;

use App\Admin\Status\SchedulerHeartbeat;
use Illuminate\Console\Events\CommandStarting;

/**
 * Notes each start of `schedule:run` ({@see SchedulerHeartbeat}). A cache
 * that cannot be reached must not stop the scheduler from running its tasks,
 * so a failure here is swallowed: the Status page then shows the scheduler
 * as stopped, and the cache as down.
 */
final class RecordSchedulerHeartbeat
{
    public function handle(CommandStarting $event): void
    {
        if ($event->command === 'schedule:run') {
            rescue(SchedulerHeartbeat::record(...), report: false);
        }
    }
}
