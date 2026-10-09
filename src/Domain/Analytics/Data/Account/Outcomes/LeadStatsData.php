<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Account\Outcomes;

/**
 * Leads captured over a window and the leads-per-conversation rate.
 */
final readonly class LeadStatsData
{
    public function __construct(
        public int $totalLeads,
        public int $conversations,
        public float $conversionRatePct,
    ) {}
}
