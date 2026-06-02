<?php

namespace Domain\Agent\Actions\Settings;

use Domain\Agent\Models\Agent;

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
