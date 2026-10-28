<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Domain;

use Domain\Agent\Models\Agent;

/**
 * Inputs for `SaveAgentDomainAction::execute()`: the agent and its new
 * custom domain, already lowercased and stripped of any scheme or path.
 */
final readonly class SaveAgentDomainData
{
    public function __construct(
        public Agent $agent,
        public string $host,
    ) {}
}
