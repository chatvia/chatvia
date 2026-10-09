<?php

declare(strict_types=1);

namespace Domain\Channel\Contracts;

use Domain\Channel\Services\Replies\TypingReplyStrategy;

/**
 * A channel whose messages can't be edited after they are sent (Telegram,
 * Messenger, WhatsApp, Instagram). The only feedback while the reply is
 * generated is the "typing…" indicator, which the platform clears after a few
 * seconds — so {@see TypingReplyStrategy} re-sends it at the channel's own
 * interval and sends the full reply at the end.
 */
interface SendsTypingIndicator extends ChannelAdapter
{
    /** Show (or refresh) the "typing…" indicator in the sender's chat. */
    public function sendTyping(): void;

    /** Re-send the indicator at most this often, in seconds. */
    public function typingRefreshSeconds(): float;
}
