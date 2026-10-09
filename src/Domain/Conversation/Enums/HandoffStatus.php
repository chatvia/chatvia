<?php

declare(strict_types=1);

namespace Domain\Conversation\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Who is answering a conversation right now: the AI, a human operator, or
 * nobody yet because the visitor asked for a human. Stored in
 * `chat_conversations.handoff_status`; the values are persisted and sent to
 * the widget, so they must not change.
 */
#[TypeScript]
enum HandoffStatus: string
{
    /** The AI answers (the default for every new conversation). */
    case Ai = 'ai';

    /** The visitor asked for a human and is waiting for an operator. */
    case Pending = 'pending';

    /** A human operator has taken over; the AI stays silent. */
    case Human = 'human';

    /** An operator handed the conversation back; the AI answers again. */
    case Returned = 'returned';
}
