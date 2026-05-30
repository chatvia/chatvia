<?php

namespace App\Http\Controllers\Agents\Models;

use App\Http\Requests\Agents\ToggleAgentModelRequest;
use Domain\Agents\Actions\ToggleAgentLlmAction;
use Domain\Agents\Models\Agent;

/**
 * Enable or disable a specific LLM on an agent.
 */
class ToggleAgentModelController
{
    public function __invoke(ToggleAgentModelRequest $request, Agent $agent, ToggleAgentLlmAction $toggle): void
    {
        $data = $request->toData();

        $toggle->execute(
            agent: $agent,
            modelExternalName: $data->model_id,
            enabled: $data->status,
        );
    }
}
