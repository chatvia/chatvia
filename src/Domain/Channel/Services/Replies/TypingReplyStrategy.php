<?php

namespace Domain\Channel\Services\Replies;

use Domain\Channel\Contracts\ChannelConversationMapping;
use Domain\Channel\Contracts\SendsTypingIndicator;
use Domain\Channel\Data\ChannelTurnData;
use Domain\Channel\Services\ChannelReplyRunner;

/**
 * Delivers a reply on a channel whose messages can't be edited (Telegram,
 * Messenger, WhatsApp, Instagram): show "typing…" the moment the message lands,
 * keep the indicator alive while the agent works (the platforms clear it after
 * a few seconds), then send the full reply at once.
 */
class TypingReplyStrategy
{
    public function __construct(
        private readonly ChannelReplyRunner $runner,
    ) {}

    /**
     * The reply is always sent, even when it is a fixed notice ("unavailable",
     * "something went wrong") instead of a model answer.
     */
    public function deliver(SendsTypingIndicator $channel, ChannelConversationMapping $conversation, string $message): void
    {
        $channel->sendTyping();
        $lastTyping = self::now();

        $reply = $this->runner->reply(
            ChannelTurnData::for($channel, $conversation, $message),
            function () use ($channel, &$lastTyping): void {
                if (self::now() - $lastTyping >= $channel->typingRefreshSeconds()) {
                    $channel->sendTyping();
                    $lastTyping = self::now();
                }
            },
        );

        $channel->sendMessage($reply);
    }

    /** Seconds since the epoch, from the app clock so tests can travel in time. */
    private static function now(): float
    {
        return now()->getPreciseTimestamp(3) / 1000;
    }
}
