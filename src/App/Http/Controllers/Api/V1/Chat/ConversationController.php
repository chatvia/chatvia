<?php

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Resources\Api\V1\Conversations\ConversationDetailData;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\ListAgentConversationsAction;
use Domain\Conversation\Actions\Removal\AdminDeleteConversationAction;
use Domain\Conversation\Data\Conversations\ConversationSummaryData;
use Domain\Conversation\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read and delete chat conversations attached to an agent.
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
    #[QueryParameter('per_page', description: 'How many conversations to return (1 to 100).', type: 'int', default: 25)]
    #[QueryParameter('cursor', description: 'The `next_cursor` of the previous page. Leave it out for the first page.', type: 'string')]
    public function index(Request $request, Agent $agent, ListAgentConversationsAction $list): array
    {
        $cursor = $request->query('cursor');

        $page = $list->execute(
            agent: $agent,
            perPage: min(max(1, $request->integer('per_page', 25)), 100),
            cursor: is_string($cursor) ? $cursor : null,
        );

        return ['data' => $page->data->all(), 'next_cursor' => $page->next_cursor];
    }

    /**
     * Get conversation
     */
    public function show(Request $request, Agent $agent, Conversation $conversation): ConversationDetailData
    {
        abort_unless($conversation->belongsToAgent($agent), 404);

        return ConversationDetailData::fromModel($conversation);
    }

    /**
     * Delete conversation
     */
    public function destroy(Request $request, Agent $agent, Conversation $conversation, AdminDeleteConversationAction $deleteConversation): JsonResponse
    {
        abort_unless($conversation->belongsToAgent($agent), 404);

        $deleteConversation->execute($agent, $conversation, $request->user());

        return response()->json(null, 204);
    }
}
