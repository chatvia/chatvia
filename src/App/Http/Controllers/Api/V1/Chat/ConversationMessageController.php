<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Http\Requests\Api\V1\Chat\SendApiMessageRequest;
use App\Http\Requests\Api\V1\CursorPageRequest;
use App\Http\Resources\Api\V1\Conversation\ConversationMessageData;
use App\Http\Responses\JsonTurnResponder;
use App\Http\Responses\SseResponder;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as DocResponse;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Streaming\Requests\StreamRequestDataFactory;
use Domain\Conversation\Services\Streaming\StreamedTurnCollector;
use Support\Pagination\CursorPage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The messages of an API conversation: send one to the agent (streamed as
 * server-sent events, or folded into one JSON reply) and page through the
 * history. `can.access.agent` turns a conversation of another agent into a
 * 404 before any of this runs.
 */
#[Group(name: 'Conversations', weight: 80)]
class ConversationMessageController
{
    /**
     * List messages
     *
     * The conversation's messages, newest first by the time they were sent.
     * Of two messages sent in the same second, the one stored later comes
     * first. The list is paged with a cursor: to get the next page, send the
     * `next_cursor` of the response as `cursor`. On the last page
     * `next_cursor` is null.
     *
     * @return array{data: list<ConversationMessageData>, next_cursor: string|null}
     */
    public function index(CursorPageRequest $request, Agent $agent, Conversation $conversation): array
    {
        // Paged by (created_at, id), so the conversation's message index
        // serves each page instead of sorting the whole conversation.
        $page = CursorPage::newestFirstBy(
            Message::query()
                ->forAgentConversation($agent->id, $conversation->id)
                ->select(['id', 'external_id', 'role', 'content', 'created_at']),
            'created_at',
            $request->perPage(),
            $request->cursor(),
        );

        return [
            'data' => array_map(ConversationMessageData::fromModel(...), $page->items()),
            'next_cursor' => $page->nextCursor()?->encode(),
        ];
    }

    /**
     * Send message
     *
     * Sends a message to the agent and answers with its reply. By default the
     * reply comes back as one JSON response once it is finished. Send
     * `stream: true` (or an `Accept: text/event-stream` header) to receive it
     * as server-sent events instead — see the Streaming section above for the
     * event format. Tool calls that need a person's approval are not available
     * over the API; such tools are skipped.
     *
     * Resending the same `request_id` within a few minutes replays the
     * finished reply instead of generating (and counting) a new one.
     */
    #[DocResponse(status: 200, description: 'The finished reply (default).', mediaType: 'application/json', type: 'array{data: MessageTurnData}')]
    #[DocResponse(status: 200, description: 'With `stream: true` or `Accept: text/event-stream`: the reply as server-sent events.', mediaType: 'text/event-stream', type: 'string')]
    public function store(
        SendApiMessageRequest $request,
        Agent $agent,
        Conversation $conversation,
        ChatStreamService $chat,
        StreamRequestDataFactory $factory,
        SseResponder $sse,
        StreamedTurnCollector $collector,
        JsonTurnResponder $json,
    ): Response {
        $conversation->loadMissing('session.chatter');

        $events = $chat->stream($factory->forApi(
            $request,
            $agent,
            $conversation->session->external_id,
            $conversation->external_id,
            $conversation->session->chatter,
        ));

        return $request->wantsStream()
            ? $sse->respond($events)
            : $json->respond($collector->collect($events), $conversation);
    }
}
