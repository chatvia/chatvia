<?php

declare(strict_types=1);

namespace Domain\Conversation\Enums;

/**
 * A thumbs-up / thumbs-down rating on an assistant reply. Stored as these
 * strings in `chat_messages.feedback` (null when cleared) and sent by every
 * chat client, so the values must not change.
 */
enum MessageFeedback: string
{
    case Positive = 'positive';
    case Negative = 'negative';
}
