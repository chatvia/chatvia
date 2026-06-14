<?php

namespace App\Http\Controllers\Agent\ChatLog;

use Domain\Agent\Actions\ChatLog\ListAgentVisitorCountriesAction;
use Domain\Agent\Data\Resources\ChatLog\Countries\ChatLogCountriesResourceData;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;

/**
 * Distinct visitor countries for the chat-log country filter.
 */
class CountryController
{
    public function __invoke(Request $request, Agent $agent, ListAgentVisitorCountriesAction $list): ChatLogCountriesResourceData
    {
        return $list->execute($agent);
    }
}
