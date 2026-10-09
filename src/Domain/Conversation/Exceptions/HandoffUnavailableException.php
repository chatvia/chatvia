<?php

namespace Domain\Conversation\Exceptions;

use RuntimeException;

/**
 * A visitor asked for a human, but this agent cannot hand off: the owner's
 * plan has no handoff, or the agent's live-chat toggle is off. The HTTP layer
 * turns it into a 403 (see bootstrap/app.php).
 */
final class HandoffUnavailableException extends RuntimeException
{
    public static function notOnPlan(): self
    {
        return new self(__('Handoff is not available on this plan.'));
    }

    public static function liveChatDisabled(): self
    {
        return new self(__('Live chat is turned off for this agent.'));
    }
}
