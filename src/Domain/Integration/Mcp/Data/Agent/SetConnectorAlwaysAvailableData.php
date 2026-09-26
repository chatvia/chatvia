<?php

namespace Domain\Integration\Mcp\Data\Agent;

use Spatie\LaravelData\Data;

class SetConnectorAlwaysAvailableData extends Data
{
    public function __construct(
        public bool $always_available,
    ) {}
}
