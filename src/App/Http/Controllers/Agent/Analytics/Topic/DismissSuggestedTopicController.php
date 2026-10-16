<?php

namespace App\Http\Controllers\Agent\Analytics\Topic;

use App\Http\Requests\Agent\Analytics\SuggestedTopicLabelRequest;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Topics\DismissSuggestedTopicAction;
use Illuminate\Http\JsonResponse;

/**
 * Dismisses an emergent topic suggestion for the agent.
 */
class DismissSuggestedTopicController
{
    public function __invoke(SuggestedTopicLabelRequest $request, Agent $agent, DismissSuggestedTopicAction $dismiss): JsonResponse
    {
        $dismiss->execute($agent, $request->label());

        return response()->json(['message' => __('Suggestion dismissed')]);
    }
}
