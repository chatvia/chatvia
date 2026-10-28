<?php

declare(strict_types=1);

namespace App\Admin\Update\Actions;

use App\Admin\Update\Data\ReleaseData;
use App\Admin\Update\Services\AvailableUpdate;
use App\Admin\Update\Services\InstalledVersion;
use App\Admin\Update\Services\ReleaseFeed;

/**
 * The daily update check: looks up the newest release and keeps it for the
 * admin panel's notice. With checks off (Settings → Platform → Updates), or
 * on an install that runs no release, nothing is asked and what was found
 * before is dropped. When the repository cannot be read, the last answer
 * stays.
 */
final readonly class CheckForUpdateAction
{
    public function __construct(
        private InstalledVersion $installed,
        private ReleaseFeed $feed,
        private AvailableUpdate $updates,
    ) {}

    /** The newer release, or null when there is none. */
    public function execute(): ?ReleaseData
    {
        if (config('chatvia.updates.check') !== true || ! $this->installed->isRelease()) {
            $this->updates->forget();

            return null;
        }

        $latest = $this->feed->latest();

        if ($latest instanceof ReleaseData) {
            $this->updates->remember($latest);
        }

        return $this->updates->get();
    }
}
