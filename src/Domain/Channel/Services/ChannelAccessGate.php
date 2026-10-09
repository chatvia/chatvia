<?php

declare(strict_types=1);

namespace Domain\Channel\Services;

use Domain\Channel\Contracts\ChannelAdapter;

/**
 * Messaging channels are for PUBLIC agents only: a channel sender (Telegram,
 * Slack, Messenger, …) is anonymous to Chatvia and can't satisfy a protected
 * agent's access model. The connect actions refuse a protected agent; this
 * gate is the shared runtime backstop for an agent flipped to protected after
 * it was already connected — every channel handler checks it before touching
 * the pipeline. Agent protection is otherwise enforced only by the web
 * middleware, which channel turns never pass through.
 */
class ChannelAccessGate
{
    /**
     * True when the channel's agent may answer. Otherwise the sender gets a
     * short "unavailable" notice (in the agent's channel language) and the
     * turn must stop.
     */
    public function allows(ChannelAdapter $channel): bool
    {
        $agent = $channel->agent();

        if ($agent->is_public) {
            return true;
        }

        $channel->sendMessage(ChannelLocale::text(
            $agent,
            'This assistant isn’t available on :channel right now.',
            ['channel' => $channel->displayName()],
        ));

        return false;
    }
}
