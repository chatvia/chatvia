<?php

namespace App\Http\Controllers\Agent\Lead;

use App\Http\Requests\Agent\Lead\UpdateLeadSettingsRequest;
use Domain\Agent\Models\Agent;
use Domain\Form\Data\LeadFieldData;

/**
 * Save which lead fields an agent asks for. Stored in the agent's `leads`
 * settings group.
 */
class AgentLeadSettingsController
{
    public function __invoke(UpdateLeadSettingsRequest $request, Agent $agent): void
    {
        $agent->setSetting('fields', array_map(
            fn (LeadFieldData $field): array => $field->toArray(),
            $request->toData(),
        ), 'leads');
    }
}
