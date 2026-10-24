<?php

use Domain\Channel\Telegram\Data\TelegramSenderData;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Telegram\Services\TelegramChatResolver;

/**
 * How the Telegram resolver names a new chat: after the sender's first name,
 * keeping their username too. The find-or-create it shares with every
 * channel is in ChannelConversationResolverTest.
 */
it('names the chat, its session and its conversation after the sender', function (TelegramSenderData $sender, ?string $firstName, ?string $username, string $title): void {
    $chat = app(TelegramChatResolver::class)->resolve(AgentTelegramBot::factory()->create(), 555, $sender);

    expect($chat->first_name)->toBe($firstName)
        ->and($chat->username)->toBe($username)
        ->and($chat->session->name)->toBe($firstName)
        ->and($chat->conversation->title)->toBe($title);
})->with([
    'a sender with a name' => [new TelegramSenderData('Sam', 'sam_t'), 'Sam', 'sam_t', 'Telegram · Sam'],
    'a sender without one' => [new TelegramSenderData, null, null, 'Telegram'],
]);
