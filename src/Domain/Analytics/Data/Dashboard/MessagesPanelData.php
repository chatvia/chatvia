<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Dashboard;

use Spatie\LaravelData\Data;

/**
 * The "Messages this month" panel: agent replies this month, the workspace's
 * monthly message limit and what is left of it (both null without a limit),
 * and the month-over-month reply trend.
 */
class MessagesPanelData extends Data
{
    public function __construct(
        public int $total,
        public ?int $limit,
        public ?int $available,
        public MessagesTrendData $trend,
    ) {}
}
