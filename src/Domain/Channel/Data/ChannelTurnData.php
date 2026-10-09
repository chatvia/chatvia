<?php

namespace Domain\Channel\Data;

use Domain\Agent\Models\Agent;
use Domain\Channel\Contracts\ChannelAdapter;
use Domain\Channel\Contracts\ChannelConversationMapping;
use Domain\Channel\Services\ChannelReplyRunner;
use Domain\Conversation\Enums\ConversationSource;

/**
 * One inbound message to run through the chat pipeline on a messaging channel:
 * which agent answers, in which Session + Conversation, from which surface.
 * The input of {@see ChannelReplyRunner}.
 */
final readonly class ChannelTurnData
{
    /**
     * @param  array<string, string>  $logContext  channel identifiers for the failure log
     */
    public function __construct(
        public Agent $agent,
        public string $sessionExternalId,
        public string $conversationExternalId,
        public string $message,
        public ConversationSource $source,
        public string $requestIdPrefix,
        public array $logContext = [],
    ) {}

    public static function for(ChannelAdapter $channel, ChannelConversationMapping $conversation, string $message): self
    {
        return new self(
            agent: $channel->agent(),
            sessionExternalId: $conversation->sessionExternalId(),
            conversationExternalId: $conversation->conversationExternalId(),
            message: $message,
            source: $channel->source(),
            requestIdPrefix: $channel->requestIdPrefix(),
            logContext: $channel->logContext(),
        );
    }
}
