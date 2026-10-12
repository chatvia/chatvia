<?php

namespace Domain\Llm\Operations\Query;

use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Data\Query\RewrittenQueryData;
use Domain\Llm\Enums\LlmRole;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\ModelRoles;
use Throwable;

/**
 * Rewrites a visitor's chat message into a clearer search query and detects
 * its language, before the knowledge-base search. Rewriting only improves
 * retrieval, so when it is switched off (`chatvia.ai.query.rewriter.enabled`)
 * or the call fails, the message is used unchanged.
 */
class QueryRewriter
{
    public function __construct(
        private readonly LlmChat $chat,
        private readonly ModelRoles $models,
    ) {}

    public function rewrite(string $userQuery, ?string $previousQuery = null): RewrittenQueryData
    {
        if (config('chatvia.ai.query.rewriter.enabled') === false) {
            return RewrittenQueryData::unchanged($userQuery);
        }

        try {
            $reply = $this->chat->send(new ChatRequestData(
                model: $this->models->model(LlmRole::Fast),
                messages: [
                    ChatMessageData::system(prompt('knowledge/rewrite-query')),
                    ChatMessageData::user(prompt('knowledge/rewrite-query-user', [
                        'user_query' => $userQuery,
                        'previous_query' => $previousQuery,
                    ])),
                ],
                temperature: 0.0,
            ));
        } catch (Throwable $e) {
            // A failed rewrite must not fail the chat turn.
            report($e);

            return RewrittenQueryData::unchanged($userQuery);
        }

        return $this->parse($reply->content, $userQuery);
    }

    /**
     * The prompt asks for `<iso-639-1>|<rewritten query>`. Models do not always
     * follow it, so anything else falls back to the original query.
     */
    private function parse(string $reply, string $userQuery): RewrittenQueryData
    {
        $reply = trim($reply);

        if (! str_contains($reply, '|')) {
            return RewrittenQueryData::unchanged($reply !== '' ? $reply : $userQuery);
        }

        [$language, $query] = array_map(trim(...), explode('|', $reply, 2));

        return new RewrittenQueryData(
            language: preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $language) === 1 ? $language : 'en',
            query: $query !== '' ? $query : $userQuery,
        );
    }
}
