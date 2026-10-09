<?php

namespace App\Http\Controllers\Agent\Analytics\Topic;

use App\Http\Requests\Agent\Analytics\SuggestedTopicLabelRequest;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Topics\PromoteSuggestedTopicAction;
use Illuminate\Http\JsonResponse;

/**
 * Turns an emergent topic suggestion into a real topic of the agent.
 */
class PromoteSuggestedTopicController
{
    public function __invoke(SuggestedTopicLabelRequest $request, Agent $agent, PromoteSuggestedTopicAction $promote): JsonResponse
    {
        $topic = $promote->execute($agent, $request->label());

        return response()->json(['message' => __('Topic added successfully'), 'topic' => $topic]);
    }
}
