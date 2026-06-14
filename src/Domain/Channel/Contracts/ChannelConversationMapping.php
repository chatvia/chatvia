<?php

namespace Domain\Channel\Contracts;

use Domain\Channel\Services\ChannelConversationResolver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A channel mapping row (TelegramChat, WhatsappChat, MessengerThread,
 * InstagramThread, SlackChannel, TeamsChat) that links a channel-native identity
 * to a persistent Chatvia Conversation.
 *
 * Lets the shared {@see ChannelConversationResolver}
 * test whether the mapped conversation still exists (it may have been
 * retention-pruned) without coupling to any one concrete mapping model.
 */
interface ChannelConversationMapping
{
    public function conversation(): BelongsTo;
}
