<?php

namespace Domain\Conversation\Exceptions;

use RuntimeException;

/**
 * A visitor asked for a human, but this agent cannot hand off: the agent's
 * live-chat toggle is off, or the install has no license key. The visitor
 * only learns that live chat is off. The HTTP layer turns it into a 403
 * (see bootstrap/app.php).
 */
final class HandoffUnavailableException extends RuntimeException
{
    public static function liveChatDisabled(): self
    {
        return new self(__('Live chat is turned off for this agent.'));
    }
}
