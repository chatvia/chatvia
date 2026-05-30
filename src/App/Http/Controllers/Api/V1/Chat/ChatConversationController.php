<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Resources\Api\V1\ChatConversationDetailData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agents\Models\Agent;
use Domain\Chat\Actions\ListAgentConversationsAction;
use Domain\Chat\Models\ChatConversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read and delete chat conversations attached to an agent.
 */
#[Group(name: 'Conversations', weight: 80)]
class ChatConversationController
{
    public function index(Request $request, Agent $agent, ListAgentConversationsAction $list): LengthAwarePaginator
    {
        return $list->execute(
            agent: $agent,
            perPage: min((int) $request->input('per_page', 25), 100),
        );
    }

    public function show(Request $request, Agent $agent, ChatConversation $conversation): ChatConversationDetailData
    {
        abort_unless($conversation->session->agent_id === $agent->id, 404);

        return ChatConversationDetailData::fromModel($conversation);
    }

    public function destroy(Request $request, Agent $agent, ChatConversation $conversation): JsonResponse
    {
        abort_unless($conversation->session->agent_id === $agent->id, 404);

        $conversation->delete();

        return response()->json(null, 204);
    }
}
