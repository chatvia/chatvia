<?php

namespace App\Http\Controllers\Agent\Source\File\Pages;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Retrieval\Services\KnowledgeSearchSwitch;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the agent's knowledge overview page, with whether the agent
 * searches its knowledge and may turn that off. Agent access is checked by
 * the route middleware.
 */
class AgentKnowledgeSourceController
{
    public function __invoke(Agent $agent, KnowledgeSearchSwitch $knowledgeSearch): Response
    {
        return Inertia::render('agents/Sources/Sources', [
            'agent' => AgentResourceData::fromModel($agent),
            'knowledgeSearch' => $knowledgeSearch->state($agent),
        ]);
    }
}
