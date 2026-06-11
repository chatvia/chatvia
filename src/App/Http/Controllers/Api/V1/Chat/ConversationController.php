<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Resources\Api\V1\Conversations\ConversationDetailData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\AdminDeleteConversationAction;
use Domain\Conversation\Actions\Conversations\ListAgentConversationsAction;
use Domain\Conversation\Models\Conversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read and delete chat conversations attached to an agent.
 */
#[Group(name: 'Conversations', weight: 80)]
class ConversationController
{
    public function index(Request $request, Agent $agent, ListAgentConversationsAction $list): LengthAwarePaginator
    {
        return $list->execute(
            agent: $agent,
            perPage: min((int) $request->input('per_page', 25), 100),
        );
    }

    public function show(Request $request, Agent $agent, Conversation $conversation): ConversationDetailData
    {
        abort_unless($conversation->session->agent_id === $agent->id, 404);

        return ConversationDetailData::fromModel($conversation);
    }

    public function destroy(Request $request, Agent $agent, Conversation $conversation, AdminDeleteConversationAction $deleteConversation): JsonResponse
    {
        abort_unless($conversation->session->agent_id === $agent->id, 404);

        $deleteConversation->execute($agent, $conversation, $request->user());

        return response()->json(null, 204);
    }
}
