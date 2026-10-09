<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Account\Outcomes;

/**
 * Escalations to a human over a window, plus the handoffs waiting right now
 * (not bound to the window).
 */
final readonly class HandoffStatsData
{
    public function __construct(
        public int $conversations,
        public int $handoffs,
        public float $handoffRatePct,
        public int $pendingNow,
    ) {}
}
