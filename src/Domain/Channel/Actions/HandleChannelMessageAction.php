<?php

namespace Domain\Channel\Actions;

use Domain\Channel\Contracts\SendsTypingIndicator;
use Domain\Channel\Contracts\StreamsReplies;
use Domain\Channel\Services\ChannelAccessGate;
use Domain\Channel\Services\Replies\StreamingReplyStrategy;
use Domain\Channel\Services\Replies\TypingReplyStrategy;

/**
 * Handles one inbound text message on any messaging channel, end to end:
 * check the agent may answer there, resolve the chat's Session +
 * Conversation, run the agent through the real pipeline, deliver the reply in
 * the channel's own way, and stamp the chat. Invoked off the webhook by each
 * channel's queued job, with that channel's adapter.
 *
 * Messaging channels are for PUBLIC agents only ({@see ChannelAccessGate}).
 */
class HandleChannelMessageAction
{
    public function __construct(
        private readonly ChannelAccessGate $gate,
        private readonly TypingReplyStrategy $typing,
        private readonly StreamingReplyStrategy $streaming,
    ) {}

    public function execute(SendsTypingIndicator|StreamsReplies $channel, string $text): void
    {
        if (! $this->gate->allows($channel)) {
            return;
        }

        $conversation = $channel->resolveConversation();

        if ($channel instanceof StreamsReplies) {
            if (! $this->streaming->deliver($channel, $conversation, $text)) {
                return;
            }
        } else {
            $this->typing->deliver($channel, $conversation, $text);
        }

        $conversation->markReplied();
    }
}
