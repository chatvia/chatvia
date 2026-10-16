<?php

namespace Domain\Conversation\Exceptions;

use RuntimeException;

/**
 * An operator tried to act inside a handed-off conversation they have not
 * taken over (or no longer hold). The HTTP layer turns it into a 403 (see
 * bootstrap/app.php).
 */
final class ConversationNotHeldException extends RuntimeException
{
    public static function forReplying(): self
    {
        return new self(__('You must take over this conversation before sending messages.'));
    }

    public static function forTyping(): self
    {
        return new self(__('You must take over this conversation before sending typing indicators.'));
    }
}
