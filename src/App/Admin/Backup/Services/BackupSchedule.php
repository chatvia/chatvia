<?php

declare(strict_types=1);

namespace App\Admin\Backup\Services;

use App\Admin\Backup\Enums\BackupFrequency;
use App\Admin\Backup\Enums\BackupStatus;
use App\Admin\Backup\Enums\BackupTrigger;
use App\Admin\Backup\Models\Backup;
use Carbon\CarbonImmutable;

/**
 * When the automatic backup is due. The scheduler asks every hour; a backup
 * is due once the newest scheduled one is as old as the chosen frequency
 * ({@see BackupFrequency}). After a failed one it tries again sooner, after
 * at most 6 hours, so one bad night does not leave a week without a backup,
 * and a failure every hour does not fill the list. Backups made by hand do
 * not move the schedule.
 */
class BackupSchedule
{
    /** The longest wait after a failed backup. */
    private const int RETRY_HOURS = 6;

    /**
     * Slack for the hourly check: a backup that started a few seconds after
     * the hour is due again at the same hour, not an hour later.
     */
    private const int SLACK_MINUTES = 5;

    public function isEnabled(): bool
    {
        return (bool) config('chatvia.backup.enabled');
    }

    public function frequency(): BackupFrequency
    {
        return BackupFrequency::tryFrom((string) config('chatvia.backup.frequency')) ?? BackupFrequency::Daily;
    }

    /** When the next automatic backup runs; null while they are off. */
    public function nextDueAt(): ?CarbonImmutable
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $last = Backup::query()
            ->where('trigger', BackupTrigger::Scheduled)
            ->latest('id')
            ->first();

        if ($last?->created_at === null) {
            return CarbonImmutable::now();
        }

        $hours = $last->status === BackupStatus::Failed
            ? min($this->frequency()->hours(), self::RETRY_HOURS)
            : $this->frequency()->hours();

        return $last->created_at->toImmutable()->addHours($hours);
    }

    public function isDue(): bool
    {
        $next = $this->nextDueAt();

        return $next !== null && $next->lessThanOrEqualTo(now()->addMinutes(self::SLACK_MINUTES));
    }
}
