<?php

namespace Domain\Channel\Services;

use Closure;
use Domain\Agent\Models\Agent;

/**
 * Messaging channels are for PUBLIC agents only: a channel sender (Telegram,
 * Slack, Messenger, …) is anonymous to Chatvia and can't satisfy a protected
 * agent's access model. Connecting is blocked for protected agents at the
 * controller; this gate is the shared runtime backstop for an agent flipped
 * to protected after it was already connected — every channel handler checks
 * it before touching the pipeline.
 */
class ChannelAccessGate
{
    /**
     * @param  Closure(): mixed  $sendUnavailable  sends the channel-specific "unavailable" notice
     */
    public function allows(?Agent $agent, Closure $sendUnavailable): bool
    {
        if ($agent?->is_public) {
            return true;
        }

        $sendUnavailable();

        return false;
    }
}
