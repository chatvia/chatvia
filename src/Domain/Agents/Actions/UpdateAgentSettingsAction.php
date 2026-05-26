<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Models\Agent;

class UpdateAgentSettingsAction
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function execute(Agent $agent, string $group, array $values): void
    {
        foreach ($values as $key => $value) {
            $agent->setSetting(key: $key, value: $value, group: $group);
        }
    }
}
