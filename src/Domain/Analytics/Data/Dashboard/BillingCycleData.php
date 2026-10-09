<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Dashboard;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

/**
 * Start and end of the workspace's active billing cycle.
 */
class BillingCycleData extends Data
{
    public function __construct(
        public CarbonInterface $started_at,
        public CarbonInterface $ends_at,
    ) {}
}
