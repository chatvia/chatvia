<?php

namespace App\Http\Controllers\Agent\Source\File\Pages;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the agent's Files knowledge page. Agent access and the
 * `agent.sources.train.files` permission are checked by the route middleware.
 */
class AgentKnowledgeFileController
{
    public function __invoke(Agent $agent): Response
    {
        return Inertia::render('dashboard/agents/pages/Sources/FilesSource', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}
