<?php

namespace Domain\Conversation\Services\Tooling\Routing;

use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;

/**
 * Returns the set of tool names called in recent assistant turns of a
 * conversation so the router can keep them available even when the
 * latest user message doesn't semantically match them — e.g. the
 * `search_issues` → `get_issue` chain where the second prompt is
 * "tell me more about #3".
 *
 * Reads from `chat_messages.metadata.tool_calls`, which is populated
 * when an assistant reply that ran one or more tools is stored.
 * If that key is missing (older messages or assistant turns that didn't
 * use tools), the message simply contributes nothing.
 *
 * Conversations are passed around the streaming layer by their external
 * UUID (`chat_conversations.external_id`), but `chat_messages` joins on
 * the internal bigint id — we translate once before querying.
 */
class StickyToolResolver
{
    /** How many recent assistant turns to scan. */
    public const RECENT_TURNS = 5;

    /**
     * @param  string  $conversationExternalId  UUID from `chat_conversations.external_id`
     * @return array<int, string> Tool names (prefixed shape used by the LLM)
     */
    public function resolve(string $conversationExternalId): array
    {
        $internalId = Conversation::query()
            ->where('external_id', $conversationExternalId)
            ->value('id');

        if ($internalId === null) {
            return [];
        }

        $messages = Message::query()
            ->where('chat_conversation_id', $internalId)
            ->where('role', MessageRole::Assistant)
            ->orderByDesc('created_at')
            ->limit(self::RECENT_TURNS)
            ->get(['metadata']);

        $names = [];
        foreach ($messages as $message) {
            $toolCalls = $message->metadata['tool_calls'] ?? null;
            if (! is_array($toolCalls)) {
                continue;
            }
            foreach ($toolCalls as $name) {
                if (is_string($name) && $name !== '') {
                    $names[$name] = true;
                }
            }
        }

        return array_keys($names);
    }
}
