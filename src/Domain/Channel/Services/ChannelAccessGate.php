<?php

declare(strict_types=1);

namespace Domain\Channel\Services;

use App\Identity\Workspace\Services\WorkspaceActivation;
use Domain\Channel\Contracts\ChannelAdapter;

/**
 * The shared runtime backstop every channel handler checks before touching
 * the pipeline (and before a chat is stored):
 *
 *  - A deactivated workspace's agents answer nowhere
 *    ({@see WorkspaceActivation}), as on the web surfaces.
 *  - Messaging channels are for PUBLIC agents only: a channel sender
 *    (Telegram, Slack, Messenger, …) is anonymous to Chatvia and can't satisfy
 *    a protected agent's access model. The connect actions refuse a protected
 *    agent; this catches an agent flipped to protected after it was already
 *    connected. Agent protection is otherwise enforced only by the web
 *    middleware, which channel turns never pass through.
 */
class ChannelAccessGate
{
    public function __construct(
        private readonly WorkspaceActivation $activation,
    ) {}

    /**
     * True when the channel's agent may answer. Otherwise the sender gets a
     * short "unavailable" notice (in the agent's channel language) and the
     * turn must stop.
     */
    public function allows(ChannelAdapter $channel): bool
    {
        $agent = $channel->agent();

        if (! $this->activation->isActive($agent->workspace_id)) {
            $channel->sendMessage(ChannelLocale::text($agent, 'Sorry — this assistant is unavailable right now. Please try again later.'));

            return false;
        }

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
