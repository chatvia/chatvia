<?php

declare(strict_types=1);

namespace Domain\Agent\Actions\Settings;

use Domain\Agent\Models\Agent;

/**
 * Sets (or clears, with null) the custom domain of an agent's Hosted Chat.
 * The format and uniqueness rules live in UpdateAgentDomainRequest.
 */
class SetAgentDomainAction
{
    public function execute(Agent $agent, ?string $domain): void
    {
        $agent->update(['domain' => $domain]);
    }
}
