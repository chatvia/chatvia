<?php

namespace App\Http\Controllers\Agent\Analytics\Topic;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Queries\Topics\SuggestedTopicsQuery;
use Illuminate\Http\JsonResponse;

/**
 * The emergent topic suggestions for the agent's Topics page.
 */
class SuggestedTopicsDataController
{
    public function __invoke(Agent $agent, SuggestedTopicsQuery $suggestedTopics): JsonResponse
    {
        return response()->json(['suggestions' => $suggestedTopics->forAgent($agent)->toArray()]);
    }
}
