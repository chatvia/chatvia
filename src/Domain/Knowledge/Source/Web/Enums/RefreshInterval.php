<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Web\Enums;

use Carbon\CarbonInterface;

/**
 * How often a website is crawled and trained again on its own, so the agent
 * learns about new and changed pages (pages that did not change are skipped
 * when trained). The value is stored (`source_websites.refresh_interval`,
 * with a CHECK), so never rename a case value.
 */
enum RefreshInterval: string
{
    case Off = 'off';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    /** Whether a website refreshed at $refreshedAt (never: null) is due at $now. */
    public function isDue(?CarbonInterface $refreshedAt, CarbonInterface $now): bool
    {
        if ($this === self::Off) {
            return false;
        }

        if (! $refreshedAt instanceof CarbonInterface) {
            return true;
        }

        return match ($this) {
            self::Daily => $refreshedAt->lte($now->copy()->subDay()),
            self::Weekly => $refreshedAt->lte($now->copy()->subWeek()),
            self::Monthly => $refreshedAt->lte($now->copy()->subMonth()),
        };
    }
}
