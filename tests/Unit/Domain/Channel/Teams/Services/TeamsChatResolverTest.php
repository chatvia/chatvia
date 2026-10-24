<?php

use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Teams\Services\TeamsChatResolver;
use Domain\Conversation\Actions\Conversations\StartConversationAction;

/**
 * What the Teams resolver adds to the find-or-create it shares with every
 * channel (ChannelConversationResolverTest): it keeps the conversation's type
 * and the sender's name, names a personal chat after the sender (a group chat
 * or channel never takes one member's name), and keeps the regional service
 * URL fresh on a returning conversation.
 */
function tcrResolver(): TeamsChatResolver
{
    return new TeamsChatResolver(app(StartConversationAction::class));
}

it('names a new chat by its type and, for a personal chat, after the sender', function (string $type, ?string $peer, ?string $sessionName, string $title): void {
    $chat = tcrResolver()->resolve(AgentTeamsBot::factory()->create(), 'a:1conv', 'https://smba.trafficmanager.net/emea/', $type, $peer);

    expect($chat->conversation_type)->toBe($type)
        ->and($chat->peer_name)->toBe($peer)
        ->and($chat->session->name)->toBe($sessionName)
        ->and($chat->conversation->title)->toBe($title);
})->with([
    'a personal chat' => ['personal', 'Lina', 'Lina', 'Teams · Lina'],
    'a personal chat without a sender name' => ['personal', null, null, 'Teams · DM'],
    'a group chat' => ['groupChat', 'Lina', null, 'Teams · Group chat'],
    'a channel' => ['channel', 'Lina', null, 'Teams · Channel'],
]);

it('refreshes the service URL when Microsoft moves the conversation', function (): void {
    $bot = AgentTeamsBot::factory()->create();
    tcrResolver()->resolve($bot, 'a:1conv', 'https://smba.trafficmanager.net/emea/', 'personal', 'Lina');

    $moved = tcrResolver()->resolve($bot, 'a:1conv', 'https://smba.trafficmanager.net/amer/', 'personal', 'Lina');

    expect($moved->fresh()->service_url)->toBe('https://smba.trafficmanager.net/amer/')
        ->and($moved::query()->count())->toBe(1);
});
