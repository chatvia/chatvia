<?php

namespace App\Http\Controllers\Agents\ChatLog;

use Domain\Agents\Actions\ChatLog\ListAgentVisitorCountriesAction;
use Domain\Agents\Data\Resources\ChatLog\Countries\ChatLogCountriesResourceData;
use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;

/**
 * Distinct visitor countries for the chat-log country filter.
 */
class CountriesController
{
    public function __invoke(Request $request, Agent $agent, ListAgentVisitorCountriesAction $list): ChatLogCountriesResourceData
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.chat.view'), 403);

        return $list->execute($agent);
    }
}
