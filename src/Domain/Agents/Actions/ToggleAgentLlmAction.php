<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\Llm;

class ToggleAgentLlmAction
{
    public function execute(Agent $agent, string $modelExternalName, bool $enabled): void
    {
        $modelId = Llm::query()->where('external_name', $modelExternalName)->valueOrFail('id');

        if ($enabled) {
            $agent->llms()->attach($modelId);
        } else {
            $agent->llms()->detach($modelId);
        }
    }
}
