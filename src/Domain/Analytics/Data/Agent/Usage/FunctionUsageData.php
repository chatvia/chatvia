<?php

namespace Domain\Analytics\Data\Agent\Usage;

/**
 * One webhook function an agent called, labelled as it was at call time, with
 * its call count and the last day it was called.
 */
final readonly class FunctionUsageData
{
    public function __construct(
        public ?string $name,
        public ?string $description,
        public int $calls,
        public ?string $lastUsedOn,
    ) {}
}
