<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Account;

use Domain\Analytics\Enums\AnalyticsPeriod;

/**
 * What an account-analytics question asks about: a named period (or explicit
 * dates, which win when both are given) and optionally one agent. Null means
 * "the default": this month and the whole account.
 */
final readonly class AnalyticsFilterData
{
    public function __construct(
        public ?AnalyticsPeriod $period = null,
        /** Inclusive start date (YYYY-MM-DD); used only together with {@see $endDate}. */
        public ?string $startDate = null,
        public ?string $endDate = null,
        /** The public id of the one agent to narrow to. */
        public ?string $agentExternalId = null,
    ) {}
}
