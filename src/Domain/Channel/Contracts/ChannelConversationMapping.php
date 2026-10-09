<?php

declare(strict_types=1);

namespace Domain\Channel\Contracts;

use Domain\Channel\Concerns\MapsChannelConversation;
use Domain\Channel\Services\ChannelConversationResolver;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A channel mapping row (TelegramChat, WhatsappChat, MessengerThread,
 * InstagramThread, SlackChannel, TeamsChat) that links a channel-native identity
 * to a persistent Chatvia Session + Conversation.
 *
 * The shared {@see ChannelConversationResolver} uses it to test whether the
 * mapped conversation still exists (it may have been retention-pruned), and the
 * reply strategies use it to address the chat pipeline and stamp the row after
 * a reply — all without coupling to one concrete mapping model. Every mapping
 * model gets the implementation from {@see MapsChannelConversation}.
 */
interface ChannelConversationMapping
{
    /** @return BelongsTo<Session, covariant Model> */
    public function session(): BelongsTo;

    /** @return BelongsTo<Conversation, covariant Model> */
    public function conversation(): BelongsTo;

    /** The external id of the Session this row maps to. */
    public function sessionExternalId(): string;

    /** The external id of the Conversation this row maps to. */
    public function conversationExternalId(): string;

    /** Record that the agent just answered on this row (`last_message_at`). */
    public function markReplied(): void;
}
