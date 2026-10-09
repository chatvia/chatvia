<?php

namespace Domain\Channel\Contracts;

use Domain\Agent\Models\Agent;
use Domain\Channel\Actions\HandleChannelMessageAction;
use Domain\Conversation\Enums\ConversationSource;

/**
 * One messaging-channel conversation as the reply pipeline sees it: the
 * connected bot / page / number that received a message, bound to the sender's
 * chat on that channel. Each channel (Telegram, Slack, Messenger, WhatsApp,
 * Microsoft Teams, Instagram) implements it once over its own API client, so
 * {@see HandleChannelMessageAction} and the reply strategies run the same turn
 * for every channel.
 *
 * Every channel can send a complete message. What it can do while the reply is
 * generated is a capability on top: {@see SendsTypingIndicator} (the reply is
 * sent once, at the end) or {@see StreamsReplies} (a placeholder is posted and
 * edited as the reply grows).
 */
interface ChannelAdapter
{
    /** The agent this channel is connected to. */
    public function agent(): Agent;

    /** The surface recorded on the conversations this channel creates. */
    public function source(): ConversationSource;

    /** The channel's product name, shown in the "unavailable on :channel" notice. */
    public function displayName(): string;

    /**
     * Find or create the Session + Conversation behind the sender's chat.
     */
    public function resolveConversation(): ChannelConversationMapping;

    /** Send a complete message (the model's Markdown) to the sender's chat. */
    public function sendMessage(string $markdown): void;

    /** Prefix of the pipeline request id, so a turn's logs show its channel. */
    public function requestIdPrefix(): string;

    /**
     * Channel identifiers added to the log line when a turn fails.
     *
     * @return array<string, string>
     */
    public function logContext(): array;
}
