<?php

namespace App\Http\Controllers\Agent\Source\File\Pages;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the agent's Markdown knowledge page, a Files screen for Markdown.
 * Agent access and the `agent.sources.train.files` permission are checked by
 * the route middleware.
 */
class AgentKnowledgeMarkdownController
{
    public function __invoke(Agent $agent): Response
    {
        return Inertia::render('agents/Sources/Markdown', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}
