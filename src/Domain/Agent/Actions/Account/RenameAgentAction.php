<?php

namespace Domain\Agent\Actions\Account;

use Domain\Agent\Data\Account\RenameAgentData;
use Domain\Agent\Models\Agent;

/**
 * Rename an agent. Every agent has a Hosted Chat surface served at
 * `/chat/{slug}`, so the slug may be renamed alongside the name.
 */
class RenameAgentAction
{
    public function execute(Agent $agent, RenameAgentData $data): Agent
    {
        $attributes = ['name' => $data->name];

        if ($data->slug !== null) {
            $attributes['slug'] = $data->slug;
        }

        $agent->update($attributes);

        return $agent->fresh();
    }
}
