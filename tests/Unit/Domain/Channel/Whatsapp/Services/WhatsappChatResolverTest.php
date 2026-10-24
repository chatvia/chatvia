<?php

use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Domain\Channel\Whatsapp\Services\WhatsappChatResolver;

/**
 * How the WhatsApp resolver names a new chat: after the profile name that
 * comes free in the webhook payload. The find-or-create it shares with every
 * channel is in ChannelConversationResolverTest.
 */
it('names the chat, its session and its conversation after the sender’s profile name', function (?string $name, string $title): void {
    $chat = app(WhatsappChatResolver::class)->resolve(AgentWhatsappNumber::factory()->create(), '4915551234567', $name);

    expect($chat->name)->toBe($name)
        ->and($chat->session->name)->toBe($name)
        ->and($chat->conversation->title)->toBe($title);
})->with([
    'a profile name' => ['Sam', 'WhatsApp · Sam'],
    'no profile name' => [null, 'WhatsApp'],
]);
