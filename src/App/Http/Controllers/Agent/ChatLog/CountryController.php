<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\ChatLog;

use Domain\Agent\Data\Resources\ChatLog\Countries\ChatLogCountriesResourceData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Queries\AgentVisitorCountriesQuery;
use Illuminate\Http\Request;

/**
 * Distinct visitor countries for the chat-log country filter.
 */
class CountryController
{
    public function __invoke(Request $request, Agent $agent, AgentVisitorCountriesQuery $list): ChatLogCountriesResourceData
    {
        return $list->get($agent);
    }
}
