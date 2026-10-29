<?php

use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Teams\Services\TeamsChatResolver;
use Domain\Conversation\Actions\Conversations\StartConversationAction;

/**
 * What the Teams resolver adds to the find-or-create it shares with every
 * channel (ChannelConversationResolverTest): it keeps the regional service
 * URL fresh on a returning conversation, as replies are sent there.
 */
function tcrResolver(): TeamsChatResolver
{
    return new TeamsChatResolver(app(StartConversationAction::class));
}

it('refreshes the service URL when Microsoft moves the conversation', function (): void {
    $bot = AgentTeamsBot::factory()->create();
    tcrResolver()->resolve($bot, 'a:1conv', 'https://smba.trafficmanager.net/emea/', 'personal', 'Lina');

    $moved = tcrResolver()->resolve($bot, 'a:1conv', 'https://smba.trafficmanager.net/amer/', 'personal', 'Lina');

    expect($moved->fresh()->service_url)->toBe('https://smba.trafficmanager.net/amer/')
        ->and($moved::query()->count())->toBe(1);
});
