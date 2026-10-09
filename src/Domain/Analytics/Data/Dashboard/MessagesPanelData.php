<?php

namespace Domain\Analytics\Data\Dashboard;

use Spatie\LaravelData\Data;

/**
 * The "Messages this cycle" panel: billed replies used, what is left, the
 * limit, the current billing cycle (null without a credit row) and the
 * month-over-month reply trend.
 */
class MessagesPanelData extends Data
{
    public function __construct(
        public int $total,
        public int $available,
        public int $limit,
        public ?BillingCycleData $cycle,
        public MessagesTrendData $trend,
    ) {}
}
