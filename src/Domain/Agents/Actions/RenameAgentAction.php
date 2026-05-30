<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Data\RenameAgentData;
use Domain\Agents\Models\Agent;

/**
 * Rename an agent. Every agent has a Hosted Chat surface served at
 * `{sub_domain}.app`, so the subdomain may be renamed alongside the name.
 */
class RenameAgentAction
{
    public function __construct(
        private readonly UpdateAgentAction $updateAgent,
    ) {}

    public function execute(Agent $agent, RenameAgentData $data): Agent
    {
        $attributes = ['name' => $data->name];

        if ($data->sub_domain !== null) {
            $attributes['sub_domain'] = $data->sub_domain;
        }

        $this->updateAgent->execute($agent, $attributes);

        return $agent->fresh();
    }
}
