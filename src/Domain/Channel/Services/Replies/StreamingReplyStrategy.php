<?php

namespace Domain\Channel\Services\Replies;

use Domain\Channel\Contracts\ChannelConversationMapping;
use Domain\Channel\Contracts\StreamsReplies;
use Domain\Channel\Data\ChannelTurnData;
use Domain\Channel\Services\ChannelLocale;
use Domain\Channel\Services\ChannelReplyRunner;

/**
 * Delivers a reply on a channel whose messages can be edited in place (Slack,
 * Microsoft Teams): post a "Thinking…" placeholder the moment the message
 * lands, rewrite it with the reply so far while the agent works (throttled to
 * the platform's edit rate limit), then land the final reply in it.
 */
class StreamingReplyStrategy
{
    public function __construct(
        private readonly ChannelReplyRunner $runner,
    ) {}

    /**
     * False when the placeholder could not be posted (bot removed from the
     * chat, missing scope, outage). The turn then never runs, so it isn't
     * billed for a reply nobody can see.
     */
    public function deliver(StreamsReplies $channel, ChannelConversationMapping $conversation, string $message): bool
    {
        $messageId = $channel->postMessage('_'.ChannelLocale::text($channel->agent(), 'Thinking…').'_');

        if ($messageId === null) {
            return false;
        }

        $lastPush = self::now();
        $lastPushed = '';

        $reply = $this->runner->reply(
            ChannelTurnData::for($channel, $conversation, $message),
            function (string $replySoFar) use ($channel, $messageId, &$lastPush, &$lastPushed): void {
                if (self::now() - $lastPush < $channel->streamThrottleSeconds()) {
                    return;
                }

                $partial = trim($replySoFar);

                if ($partial !== '' && $partial !== $lastPushed) {
                    $channel->updateMessage($messageId, $partial);
                    $lastPushed = $partial;
                }

                $lastPush = self::now();
            },
        );

        $channel->updateMessage($messageId, $reply);

        return true;
    }

    /** Seconds since the epoch, from the app clock so tests can travel in time. */
    private static function now(): float
    {
        return now()->getPreciseTimestamp(3) / 1000;
    }
}
