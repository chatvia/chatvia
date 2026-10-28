<?php

namespace App\Http\Controllers\Agent\Source\Video;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the agent's Video knowledge page. Agent access and the
 * `agent.sources.train.video` permission are checked by the route middleware.
 */
class AgentSourceVideoController
{
    public function __invoke(Agent $agent): Response
    {
        return Inertia::render('dashboard/agents/pages/Sources/Video', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}
