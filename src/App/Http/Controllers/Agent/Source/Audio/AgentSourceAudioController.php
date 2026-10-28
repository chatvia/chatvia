<?php

namespace App\Http\Controllers\Agent\Source\Audio;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the agent's Audio knowledge page. Agent access and the
 * `agent.sources.train.audio` permission are checked by the route middleware.
 */
class AgentSourceAudioController
{
    public function __invoke(Agent $agent): Response
    {
        return Inertia::render('dashboard/agents/pages/Sources/Audio', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}
