<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Web\Actions\Refresh;

use Domain\Knowledge\Source\Web\Enums\RefreshInterval;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;

/**
 * Sets how often a website is crawled and trained again on its own. Switching
 * it on counts from now, so it never starts a refresh at once; moving between
 * intervals counts from the last refresh.
 */
class SetWebsiteRefreshIntervalAction
{
    public function execute(SourceWebsite $website, RefreshInterval $interval): SourceWebsite
    {
        $switchingOn = $website->refresh_interval === RefreshInterval::Off && $interval !== RefreshInterval::Off;

        $website->update([
            'refresh_interval' => $interval,
            'refreshed_at' => $switchingOn ? now() : $website->refreshed_at,
        ]);

        return $website;
    }
}
