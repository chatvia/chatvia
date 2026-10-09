<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Settings;

use Spatie\LaravelData\Data;

/**
 * The custom domain for an agent's Hosted Chat, or null to remove it.
 */
class UpdateAgentDomainData extends Data
{
    public function __construct(
        public ?string $domain,
    ) {}
}
