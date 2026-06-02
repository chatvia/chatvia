<?php

namespace Domain\Agent\Data\Settings;

use Spatie\LaravelData\Data;

class UpdateAgentDomainData extends Data
{
    public function __construct(
        public ?string $domain,
    ) {}
}
