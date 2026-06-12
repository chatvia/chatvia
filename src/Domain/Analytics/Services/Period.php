<?php

namespace Domain\Analytics\Services;

use Carbon\CarbonImmutable;

/**
 * An inclusive [start, end] window resolved from a tool's `period` argument.
 * `label` is a human phrase ("this billing cycle", "the last 7 days") that the
 * analytics tools weave into their text summary so the LLM answers naturally.
 */
final readonly class Period
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public string $key,
        public string $label,
    ) {}

    /** Inclusive day count, floored at 1. */
    public function days(): int
    {
        return max(1, (int) $this->start->startOfDay()->diffInDays($this->end->endOfDay()) + 1);
    }

    /**
     * The equally-long window immediately before this one — used for
     * "up or down vs the previous period" trend deltas.
     */
    public function previousWindow(): self
    {
        $length = $this->start->diffInSeconds($this->end);

        return new self(
            $this->start->subSeconds($length + 1),
            $this->start->subSecond(),
            $this->key.'_previous',
            'the prior period',
        );
    }
}
