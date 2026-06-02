<?php

namespace Domain\Agent\Data\Account;

use Domain\Agent\Models\Agent;
use Spatie\LaravelData\Data;

class CreateAgentResult extends Data
{
    public function __construct(
        public Agent $agent,
    ) {}
}
