<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Requests\Api\V1\Chat\StoreApiConversationRequest;
use App\Http\Requests\Api\V1\CursorPageRequest;
use App\Http\Resources\Api\V1\Conversation\ConversationDetailData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\CreateApiConversationAction;
use Domain\Conversation\Actions\Removal\AdminDeleteConversationAction;
use Domain\Conversation\Data\Conversations\ConversationSummaryData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Queries\AgentConversationsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Create, read and delete chat conversations attached to an agent.
 * `can.access.agent` turns a conversation of another agent into a 404.
 * Show and destroy accept a hidden conversation's id on purpose: an API
 * conversation stays hidden until its first reply, and its creator must be
 * able to read it back meanwhile.
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
    public function index(CursorPageRequest $request, Agent $agent, AgentConversationsQuery $list): array
    {
        $page = $list->get(
            agent: $agent,
            perPage: $request->perPage(),
            cursor: $request->cursor(),
        );

        return ['data' => $page->data->all(), 'next_cursor' => $page->next_cursor];
    }

    /**
     * Create conversation
     *
     * Opens a conversation with the agent, ready for `POST …/messages`. It
     * stays hidden from the conversation list until the agent's first reply,
     * and expires after a day when it never gets one. Pass `chatter_id` to
     * attribute the conversation to one of your chatters.
     */
    public function store(StoreApiConversationRequest $request, Agent $agent, CreateApiConversationAction $create): JsonResponse
    {
        $conversation = $create->execute($request->toData($agent));

        return response()->json(['data' => ConversationSummaryData::fromModel($conversation)], 201);
    }

    /**
     * Get conversation
     *
     * The conversation with its first 500 messages, oldest first. For longer
     * conversations, page through `GET …/conversations/{id}/messages`.
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
    public function destroy(Request $request, Agent $agent, Conversation $conversation, AdminDeleteConversationAction $deleteConversation): Response
    {
        $deleteConversation->execute($agent, $conversation, $request->user());

        return response()->noContent();
    }
}
