<?php

namespace App\Http\Controllers\Agent\Analytics\Topic;

use App\Http\Requests\Agent\Analytics\SuggestedTopicLabelRequest;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
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
        // not generated again. All of the agent's conversations, not only the
        // suggestion window, so a later promote of the same label cannot
        // bring the dismissed rows back.
        ConversationTopic::query()
            ->where('suggested_label', $request->label())
            ->whereIn('chat_conversation_id', Conversation::query()->select('id')->where('agent_id', $agent->id))
            ->delete();

        return response()->json(['message' => __('Suggestion dismissed')]);
    }
}
