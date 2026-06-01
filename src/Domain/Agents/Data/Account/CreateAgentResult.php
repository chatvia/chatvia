<?php

namespace Domain\Agents\Data\Account;

use Domain\Agents\Models\Agent;
use Spatie\LaravelData\Data;

class CreateAgentResult extends Data
{
    public function __construct(
        public Agent $agent,
    ) {}
}
