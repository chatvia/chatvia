<?php

namespace App\Console\Commands;

use Domain\Notification\Actions\PruneNotificationsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scheduled retention for the dashboard's notification center (see
 * {@see PruneNotificationsAction}).
 */
#[Description('Delete dashboard notifications with no activity in the retention window.')]
#[Signature('app:prune-notifications {--days= : Retention in days; defaults to config(chatvia.notifications.retention_days)}')]
class PruneNotifications extends Command
{
    public function handle(PruneNotificationsAction $prune): int
    {
        $days = $this->option('days') !== null
            ? max(0, (int) $this->option('days'))
            : max(1, (int) config('chatvia.notifications.retention_days', 7));

        $deleted = $prune->execute($days);

        $this->info("Pruned {$deleted} notification(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
