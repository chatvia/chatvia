<?php

namespace Domain\Llm\Operations\Query;

use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Data\Query\RewrittenQueryData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\TaskModels;
use Throwable;

/**
 * Turns a follow-up message ("and the price?") into one search question that
 * makes sense without the chat, from the last turns before it, and detects
 * its language. The result is used only to search the knowledge; the model
 * still answers the message as the visitor wrote it. A message with no chat
 * before it is searched as it is, without a model call. When it is switched
 * off (`chatvia.ai.query.rewriter.enabled`) or the call fails, the message is
 * used unchanged.
 */
class QueryRewriter
{
    /** How many earlier messages (visitor and agent) the rewrite sees. */
    private const int HISTORY_MESSAGES = 6;

    /** Each earlier message is cut to this many characters: the gist is enough. */
    private const int MESSAGE_CHARS = 500;

    public function __construct(
        private readonly LlmChat $chat,
        private readonly TaskModels $models,
    ) {}

    /**
     * @param  list<array{role: string, content: mixed}>  $history  the chat before the message, oldest first
     */
    public function rewrite(string $message, array $history): RewrittenQueryData
    {
        $conversation = $this->conversation($history);

        // A preset in .env may read "1" or "true".
        if ($conversation === '' || ! filter_var(config('chatvia.ai.query.rewriter.enabled'), FILTER_VALIDATE_BOOL)) {
            return RewrittenQueryData::unchanged($message);
        }

        try {
            $reply = $this->chat->send(new ChatRequestData(
                model: $this->models->model(LlmTask::FollowUp),
                tag: AiCallTag::FollowUpRewrite,
                messages: [
                    ChatMessageData::system(prompt('knowledge/rewrite-query')),
                    ChatMessageData::user(prompt('knowledge/rewrite-query-user', [
                        'conversation' => $conversation,
                        'message' => $message,
                    ])),
                ],
                temperature: 0.0,
            ));
        } catch (Throwable $e) {
            // A failed rewrite must not fail the chat turn.
            report($e);

            return RewrittenQueryData::unchanged($message);
        }

        return $this->parse($reply->content, $message);
    }

    /**
     * The last turns, one per line, as `Visitor: …` / `Agent: …`.
     *
     * @param  list<array{role: string, content: mixed}>  $history
     */
    private function conversation(array $history): string
    {
        $lines = [];

        foreach (array_slice($history, -self::HISTORY_MESSAGES) as $entry) {
            $content = is_string($entry['content'] ?? null) ? trim((string) preg_replace('/\s+/u', ' ', $entry['content'])) : '';

            if ($content === '' || ! in_array($entry['role'] ?? null, ['user', 'assistant', 'agent'], true)) {
                continue;
            }

            $lines[] = ($entry['role'] === 'user' ? 'Visitor: ' : 'Agent: ').mb_strimwidth($content, 0, self::MESSAGE_CHARS, '…');
        }

        return implode("\n", $lines);
    }

    /**
     * The prompt asks for `<iso-639-1>|<question>`. A reply without the pipe
     * is taken as the question itself; an empty one keeps the message.
     */
    private function parse(string $reply, string $message): RewrittenQueryData
    {
        $reply = trim($reply);

        if (! str_contains($reply, '|')) {
            return RewrittenQueryData::unchanged($reply !== '' ? $reply : $message);
        }

        [$language, $query] = array_map(trim(...), explode('|', $reply, 2));

        return new RewrittenQueryData(
            language: preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $language) === 1 ? $language : 'en',
            query: $query !== '' ? $query : $message,
        );
    }
}
