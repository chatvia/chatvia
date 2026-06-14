<?php

namespace App\Http\Controllers\Agent\ChatLog;

use Domain\Agent\Data\Resources\ChatLog\Conversations\ChatLogConversationsResourceData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\AdminDeleteConversationAction;
use Domain\Conversation\Models\Conversation;
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
        $conversation = Conversation::query()
            ->forAgent($agent->id)
            ->where('external_id', $conversationId)
            ->firstOrFail();

        $deleteConversation->execute($agent, $conversation, $request->user());

        return response()->json(['success' => true, 'message' => 'Conversation deleted successfully.']);
    }
}
