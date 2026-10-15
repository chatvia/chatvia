<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Requests\Api\V1\CursorPageRequest;
use App\Http\Resources\Api\V1\Conversations\ConversationDetailData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\ListAgentConversationsAction;
use Domain\Conversation\Actions\Removal\AdminDeleteConversationAction;
use Domain\Conversation\Data\Conversations\ConversationSummaryData;
use Domain\Conversation\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read and delete chat conversations attached to an agent.
 * `can.access.agent` turns a conversation of another agent into a 404.
 */
#[Group(name: 'Conversations', weight: 80)]
class ConversationController
{
    /**
     * List conversations
     *
     * The agent's conversations, newest activity first. The list is paged with
     * a cursor: to get the next page, send the `next_cursor` of the response as
     * `cursor`. On the last page `next_cursor` is null.
     *
     * @return array{data: list<ConversationSummaryData>, next_cursor: string|null}
     */
    public function index(CursorPageRequest $request, Agent $agent, ListAgentConversationsAction $list): array
    {
        $page = $list->execute(
            agent: $agent,
            perPage: $request->perPage(),
            cursor: $request->cursor(),
        );

        return ['data' => $page->data->all(), 'next_cursor' => $page->next_cursor];
    }

    /**
     * Get conversation
     *
     * The conversation with its first 500 messages, oldest first.
     *
     * @return array{data: ConversationDetailData}
     */
    public function show(Agent $agent, Conversation $conversation): array
    {
        return ['data' => ConversationDetailData::fromModel($conversation)];
    }

    /**
     * Delete conversation
     */
    public function destroy(Request $request, Agent $agent, Conversation $conversation, AdminDeleteConversationAction $deleteConversation): JsonResponse
    {
        $deleteConversation->execute($agent, $conversation, $request->user());

        return response()->json(null, 204);
    }
}
