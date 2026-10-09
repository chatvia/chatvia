<?php

namespace Domain\Conversation\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Who wrote a chat message. Stored in `chat_messages.role` and in the cached
 * conversation history; the values are persisted and read by every chat
 * client, so they must not change.
 *
 * Note the naming: the stored value `agent` means a HUMAN operator who took
 * over the conversation (handoff), not the AI agent. The AI's replies are
 * `assistant`. The case is called Operator so code reads correctly.
 */
#[TypeScript]
enum MessageRole: string
{
    /** The visitor or chatter. */
    case User = 'user';

    /** The AI agent's reply. */
    case Assistant = 'assistant';

    /** A human operator replying during a handoff (stored as `agent`). */
    case Operator = 'agent';
}
