<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Analytics\Unanswered;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The agent's "Unanswered" page: replies with no knowledge found or a
 * thumbs-down, to fix with "Revise answer". The list loads from
 * {@see AgentUnansweredDataController}.
 */
class AgentUnansweredController
{
    public function __invoke(Agent $agent): Response
    {
        return Inertia::render('agents/Unanswered', [
            'agent' => AgentResourceData::fromModel($agent),
            // Without the chat log no replies are stored, so the list stays empty.
            'chatlogEnabled' => $agent->chatlogEnabled(),
        ]);
    }
}
