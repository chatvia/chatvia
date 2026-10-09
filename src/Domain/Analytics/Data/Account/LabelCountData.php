<?php

namespace Domain\Analytics\Data\Account;

/**
 * A labelled count in a ranked or bucketed breakdown — a weekday, a device
 * type, a topic, a function name — and how many of it there were.
 */
final readonly class LabelCountData
{
    public function __construct(
        public string $label,
        public int $count,
    ) {}
}
