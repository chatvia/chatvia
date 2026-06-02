<?php

namespace App\Http\Controllers\Agent\ChatLog;

use Domain\Agent\Data\Resources\ChatLog\Conversations\ChatLogConversationsResourceData;
use Domain\Agent\Models\Agent;
use Domain\Chat\Actions\Conversations\AdminDeleteConversationAction;
use Domain\Chat\Models\ChatConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * List and delete conversations in an agent's chat log. Listing supports the
 * full filter set (topic / sentiment / tone / date / country / source / …)
 * driving the chat-log page.
 */
class ConversationController
{
    public function index(Request $request, Agent $agent): ChatLogConversationsResourceData
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.chat.view'), 403);

        return ChatLogConversationsResourceData::fromAgent(
            agent: $agent,
            page: $request->integer('page', 1),
            topicFilter: $request->query('topic'),
            sentimentFilter: $request->query('sentiment'),
            toneFilter: $request->query('tone'),
            startDate: $request->query('start_date'),
            endDate: $request->query('end_date'),
            countryFilter: $request->query('country'),
            confidenceScoreFilter: $request->query('confidence_score'),
            feedbackFilter: $request->query('feedback'),
            sourceFilter: $request->query('source'),
            onlineOnly: $request->boolean('online_only'),
        );
    }

    public function destroy(
        Request $request,
        Agent $agent,
        string $conversationId,
        AdminDeleteConversationAction $deleteConversation,
    ): JsonResponse {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.chat.delete'), 403);

        $conversation = ChatConversation::query()
            ->whereHas('session', fn ($q) => $q->where('agent_id', $agent->id))
            ->where('external_id', $conversationId)
            ->firstOrFail();

        $deleteConversation->execute($agent, $conversation);

        return response()->json(['success' => true, 'message' => 'Conversation deleted successfully.']);
    }
}
