<?php

namespace Domain\Analytics\Data\Dashboard;

use Spatie\LaravelData\Data;

/**
 * The plan-usage panel: the plan name and, per quota, what is used, the limit
 * and what remains.
 */
class UsageStatsData extends Data
{
    public function __construct(
        public string $plan,
        public QuotaUsageData $messages,
        public QuotaUsageData $pages,
        public QuotaUsageData $minutes,
        public QuotaUsageData $links,
    ) {}
}
