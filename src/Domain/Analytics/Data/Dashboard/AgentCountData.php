<?php

namespace Domain\Analytics\Data\Dashboard;

use Spatie\LaravelData\Data;

/**
 * How many agents the workspace has.
 */
class AgentCountData extends Data
{
    public function __construct(
        public int $total,
    ) {}
}
