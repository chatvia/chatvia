<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Account\Activity;

/**
 * How fast the AI replied over a window, in milliseconds (rounded), plus the
 * average time from a conversation's first visitor message to its first
 * reply, in seconds. Averages are null when nothing was measured.
 */
final readonly class ResponseTimeStatsData
{
    public function __construct(
        public int $repliesMeasured,
        public int|float|null $avgMs,
        public int|float|null $p50Ms,
        public int|float|null $p95Ms,
        public ?int $maxMs,
        public int|float|null $avgFirstResponseSeconds,
    ) {}
}
