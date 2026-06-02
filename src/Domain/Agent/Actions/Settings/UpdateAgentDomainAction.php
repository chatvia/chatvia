<?php

namespace Domain\Agent\Actions\Settings;

use Domain\Agent\Data\Settings\UpdateAgentDomainData;
use Domain\Agent\Models\Agent;

/**
 * Updates the agent's custom hostname (the value the user has pointed at
 * the agent via CNAME). Pass null to clear. Quota enforcement happens at
 * the controller layer where the workspace context is available.
 */
class UpdateAgentDomainAction
{
    public function execute(Agent $agent, UpdateAgentDomainData $data): void
    {
        $domain = $data->domain !== null && $data->domain !== ''
            ? strtolower(trim($data->domain))
            : null;

        $agent->update(['domain' => $domain]);
    }
}
