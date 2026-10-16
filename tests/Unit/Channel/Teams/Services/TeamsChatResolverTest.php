<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Teams\Models\TeamsChat;
use Domain\Channel\Teams\Services\TeamsChatResolver;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Str;

/**
 * The resolver maps a Teams (bot, conversation_id) pair onto a persistent
 * Chatvia Session + Conversation, so a personal chat / group chat / channel
 * thread keeps one ongoing conversation across messages. First contact
 * creates them; later messages reuse the same mapping and keep the regional
 * `service_url` fresh.
 */
function teamsResolverBot(): AgentTeamsBot
{
    $agent = Agent::factory()->create();

    return AgentTeamsBot::create([
        'agent_id' => $agent->id,
        'app_id' => 'f5e1f9d2-1111-2222-3333-444455556666',
        'app_secret' => 'client-secret-value',
        'tenant_id' => null,
        'webhook_secret' => Str::random(48),
        'enabled' => true,
    ]);
}

function teamsResolver(): TeamsChatResolver
{
    return new TeamsChatResolver(app(StartConversationAction::class));
}

it('creates a Session + Conversation + mapping on first contact', function (): void {
    $bot = teamsResolverBot();

    $chat = teamsResolver()->resolve($bot, 'a:1conv', 'https://smba.trafficmanager.net/emea/', 'personal', 'Lina');

    expect($chat->agent_teams_bot_id)->toBe($bot->id)
        ->and($chat->conversation_id)->toBe('a:1conv')
        ->and($chat->service_url)->toBe('https://smba.trafficmanager.net/emea/')
        ->and($chat->conversation_type)->toBe('personal')
        ->and($chat->peer_name)->toBe('Lina')
        ->and($chat->chat_session_id)->not->toBeNull()
        ->and($chat->chat_conversation_id)->not->toBeNull();

    // The Session is real and belongs to the agent, with no chatter user.
    $session = $chat->session;
    expect($session->agent_id)->toBe($bot->agent_id)
        ->and($session->user_id)->toBeNull()
        ->and($session->name)->toBe('Lina');
});

it('reuses the same mapping for a returning conversation', function (): void {
    $bot = teamsResolverBot();
    $resolver = teamsResolver();

    $first = $resolver->resolve($bot, 'a:1conv', 'https://smba.trafficmanager.net/emea/', 'personal', 'Lina');
    $second = $resolver->resolve($bot, 'a:1conv', 'https://smba.trafficmanager.net/emea/', 'personal', 'Lina');

    expect($second->id)->toBe($first->id);
    expect(TeamsChat::query()->count())->toBe(1);
});

it('refreshes the service url when Microsoft moves the conversation', function (): void {
    $bot = teamsResolverBot();
    $resolver = teamsResolver();

    $resolver->resolve($bot, 'a:1conv', 'https://smba.trafficmanager.net/emea/', 'personal', 'Lina');
    $moved = $resolver->resolve($bot, 'a:1conv', 'https://smba.trafficmanager.net/amer/', 'personal', 'Lina');

    expect($moved->service_url)->toBe('https://smba.trafficmanager.net/amer/');
    expect(TeamsChat::query()->count())->toBe(1);
});

it('keeps separate conversations for different Teams conversations', function (): void {
    $bot = teamsResolverBot();
    $resolver = teamsResolver();

    $a = $resolver->resolve($bot, 'a:1conv', 'https://smba.trafficmanager.net/emea/', 'personal', 'Lina');
    $b = $resolver->resolve($bot, '19:channel@thread.tacv2;messageid=42', 'https://smba.trafficmanager.net/emea/', 'channel', null);

    expect($a->id)->not->toBe($b->id)
        ->and($a->chat_conversation_id)->not->toBe($b->chat_conversation_id);
    expect(TeamsChat::query()->count())->toBe(2);
});

it('tags the conversation as a Microsoft Teams conversation', function (): void {
    $bot = teamsResolverBot();

    $chat = teamsResolver()->resolve($bot, 'a:1conv', 'https://smba.trafficmanager.net/emea/', 'personal', 'Lina');

    expect(Conversation::query()
        ->where('external_id', $chat->conversation->external_id)
        ->where('source', ConversationSource::MsTeams)
        ->exists())->toBeTrue();
});

it('copes with group chats, channels, and a missing sender name', function (): void {
    $bot = teamsResolverBot();
    $resolver = teamsResolver();

    $group = $resolver->resolve($bot, '19:group@thread.v2', 'https://smba.trafficmanager.net/emea/', 'groupChat', 'Lina');
    $anonymous = $resolver->resolve($bot, 'a:2conv', 'https://smba.trafficmanager.net/emea/', 'personal', null);

    // Group chats never adopt a single member's name; a nameless personal chat still resolves.
    $groupSession = $group->session;
    expect($groupSession->name)->toBeNull();
    expect($anonymous->peer_name)->toBeNull();
    expect(TeamsChat::query()->count())->toBe(2);
});
