<?php

namespace App\Http\Controllers\Agent\Analytics\Topic;

use App\Http\Requests\Agent\Analytics\SuggestedTopicLabelRequest;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Illuminate\Http\JsonResponse;

/**
 * Dismisses an emergent topic suggestion for the agent.
 */
class DismissSuggestedTopicController
{
    public function __invoke(SuggestedTopicLabelRequest $request, Agent $agent): JsonResponse
    {
        // Drop the suggestion rows for this agent — a single delete, so it stays
        // inline. The owning conversations keep analyzed_at, so the label is
        // not generated again.
        ConversationTopic::query()
            ->where('suggested_label', $request->label())
            ->whereHas('conversation.session', fn ($query) => $query->where('agent_id', $agent->id))
            ->delete();

        return response()->json(['message' => __('Suggestion dismissed')]);
    }
}
