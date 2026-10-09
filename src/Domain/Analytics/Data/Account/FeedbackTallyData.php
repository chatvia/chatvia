<?php

namespace Domain\Analytics\Data\Account;

/**
 * Thumbs up and thumbs down given on AI replies in a window.
 */
final readonly class FeedbackTallyData
{
    public function __construct(
        public int $positive,
        public int $negative,
    ) {}

    public function rated(): int
    {
        return $this->positive + $this->negative;
    }
}
