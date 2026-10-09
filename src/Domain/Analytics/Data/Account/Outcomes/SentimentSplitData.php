<?php

namespace Domain\Analytics\Data\Account\Outcomes;

/**
 * Messages per analysed sentiment over a window.
 */
final readonly class SentimentSplitData
{
    public function __construct(
        public int $positive,
        public int $neutral,
        public int $negative,
    ) {}

    public function total(): int
    {
        return $this->positive + $this->neutral + $this->negative;
    }
}
