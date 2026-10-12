<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Platform;

/**
 * A count over a window and the same count over the equally long window
 * before it, for "up or down" trends.
 */
final readonly class CountTrendData
{
    public function __construct(
        public int $current,
        public int $previous,
    ) {}

    /**
     * The change in percent, or null when there is nothing to compare with.
     */
    public function changePct(): ?float
    {
        return $this->previous > 0
            ? round((($this->current - $this->previous) / $this->previous) * 100, 1)
            : null;
    }
}
