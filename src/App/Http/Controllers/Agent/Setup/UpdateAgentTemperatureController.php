<?php

namespace App\Http\Controllers\Agent\Setup;

use App\Http\Requests\Agent\Crud\UpdateAgentTemperatureRequest;
use Domain\Agent\Models\Agent;

/**
 * Update only the agent's answer "temperature" (an `llm_settings` hyperparameter).
 * The model itself is platform-managed and deliberately never exposed or editable
 * by the owner — they tune answer style via temperature alone. A single trivial
 * setting write, so it stays inline (no Action).
 */
class UpdateAgentTemperatureController
{
    public function __invoke(UpdateAgentTemperatureRequest $request, Agent $agent): void
    {
        $agent->setSetting('temperature', (float) $request->validated('temperature'), 'llm_settings');
    }
}
