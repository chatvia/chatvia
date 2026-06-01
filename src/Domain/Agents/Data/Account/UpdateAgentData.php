<?php

namespace Domain\Agents\Data\Account;

use Spatie\LaravelData\Data;

class UpdateAgentData extends Data
{
    public function __construct(
        public string $name,
        public string $sub_domain,
        public string $system_instructions,
        public bool $is_public,
    ) {}
}
