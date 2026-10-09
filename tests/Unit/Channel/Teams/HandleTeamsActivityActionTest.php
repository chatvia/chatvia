<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Teams\Actions\HandleTeamsActivityAction;
use Domain\Channel\Teams\Data\TeamsActivityData;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Teams\Models\TeamsChat;
use Domain\Channel\Teams\Services\TeamsAdapter;
use Domain\Channel\Teams\Services\TeamsChatResolver;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Illuminate\Support\Str;
use Tests\Support\Channel\StubChannelReplyRunner;
use Tests\Support\Teams\FakeTeamsClient;

/**
 * The handle Action wires the inbound activity end to end: strip the bot's
 * own @mention, resolve the chat, post a placeholder, stream the reply into
 * it via activity edits, and stamp the chat. The pipeline (real LLM) is
 * stubbed here — this asserts the wiring, not the model's answer.
 */
function teamsHandleBot(bool $public = true): AgentTeamsBot
{
    $agent = Agent::factory()->create(['is_public' => $public]);

    return AgentTeamsBot::create([
        'agent_id' => $agent->id,
        'app_id' => 'f5e1f9d2-1111-2222-3333-444455556666',
        'app_secret' => 'client-secret-value',
        'tenant_id' => null,
        'webhook_secret' => Str::random(48),
        'enabled' => true,
    ]);
}

/**
 * Run one Teams activity through the handle action, wired to the stubbed
 * pipeline.
 *
 * @param  array<string, mixed>  $activity
 */
function teamsHandle(FakeTeamsClient $client, StubChannelReplyRunner $runner, AgentTeamsBot $bot, array $activity): void
{
    new HandleTeamsActivityAction($runner->handler())->execute(
        new TeamsAdapter($client, new TeamsChatResolver(app(StartConversationAction::class)), $bot, TeamsActivityData::fromArray($activity)),
    );
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function teamsHandleActivity(array $overrides = []): array
{
    return array_replace_recursive([
        'type' => 'message',
        'id' => 'act-100',
        'text' => 'hi there',
        'serviceUrl' => 'https://smba.trafficmanager.net/emea/',
        'conversation' => ['id' => 'a:1conversation', 'conversationType' => 'personal'],
        'from' => ['id' => '29:user-aad', 'name' => 'Lina'],
        'recipient' => ['id' => '28:f5e1f9d2-1111-2222-3333-444455556666'],
    ], $overrides);
}

it('posts a placeholder, streams partials into it, and lands the final reply', function (): void {
    $bot = teamsHandleBot();
    $client = new FakeTeamsClient;
    $runner = new StubChannelReplyRunner('Hello from the agent', ['Hello', 'Hello from']);

    teamsHandle($client, $runner, $bot, teamsHandleActivity());

    // One placeholder post, then a partial edit per push + the final edit.
    expect($client->posted)->toHaveCount(1)
        ->and($client->posted[0]['serviceUrl'])->toBe('https://smba.trafficmanager.net/emea/')
        ->and($client->posted[0]['conversationId'])->toBe('a:1conversation');

    expect($client->updates)->toHaveCount(3)
        ->and($client->updates[0]['markdown'])->toBe('Hello')
        ->and($client->updates[1]['markdown'])->toBe('Hello from')
        ->and($client->updates[2]['markdown'])->toBe('Hello from the agent')
        ->and($client->updates[2]['activityId'])->toBe($client->nextActivityId);

    expect($runner->received())->toBe('hi there');

    $chat = TeamsChat::query()->firstOrFail();
    expect($chat->conversation_id)->toBe('a:1conversation')
        ->and($chat->last_message_at)->not->toBeNull();
});

it('strips the bot’s own mention through the mention entity', function (): void {
    $bot = teamsHandleBot();
    $client = new FakeTeamsClient;
    $runner = new StubChannelReplyRunner('ok');

    $activity = teamsHandleActivity([
        'text' => '<at>Support Bot</at> what are your opening hours?',
        'conversation' => ['id' => '19:channel@thread.tacv2;messageid=42', 'conversationType' => 'channel'],
        'entities' => [
            [
                'type' => 'mention',
                'text' => '<at>Support Bot</at>',
                'mentioned' => ['id' => '28:f5e1f9d2-1111-2222-3333-444455556666', 'name' => 'Support Bot'],
            ],
        ],
    ]);

    teamsHandle($client, $runner, $bot, $activity);

    expect($runner->received())->toBe('what are your opening hours?');
});

it('unwraps other people’s mentions instead of dropping them', function (): void {
    $bot = teamsHandleBot();
    $client = new FakeTeamsClient;
    $runner = new StubChannelReplyRunner('ok');

    $activity = teamsHandleActivity([
        'text' => '<at>Support Bot</at> summarize what <at>Lina</at> asked',
        'entities' => [
            [
                'type' => 'mention',
                'text' => '<at>Support Bot</at>',
                'mentioned' => ['id' => '28:f5e1f9d2-1111-2222-3333-444455556666', 'name' => 'Support Bot'],
            ],
            [
                'type' => 'mention',
                'text' => '<at>Lina</at>',
                'mentioned' => ['id' => '29:user-aad', 'name' => 'Lina'],
            ],
        ],
    ]);

    teamsHandle($client, $runner, $bot, $activity);

    expect($runner->received())->toBe('summarize what Lina asked');
});

it('keeps one chat mapping across multiple inbound activities', function (): void {
    $bot = teamsHandleBot();
    $client = new FakeTeamsClient;
    $runner = new StubChannelReplyRunner('ok');

    teamsHandle($client, $runner, $bot, teamsHandleActivity(['id' => 'act-1', 'text' => 'first']));
    teamsHandle($client, $runner, $bot, teamsHandleActivity(['id' => 'act-2', 'text' => 'second']));

    expect(TeamsChat::query()->count())->toBe(1);
    expect($client->posted)->toHaveCount(2);
});

it('does nothing when the message is only the mention itself', function (): void {
    $bot = teamsHandleBot();
    $client = new FakeTeamsClient;
    $runner = new StubChannelReplyRunner('should never run');

    $activity = teamsHandleActivity([
        'text' => '<at>Support Bot</at>',
        'entities' => [
            [
                'type' => 'mention',
                'text' => '<at>Support Bot</at>',
                'mentioned' => ['id' => '28:f5e1f9d2-1111-2222-3333-444455556666', 'name' => 'Support Bot'],
            ],
        ],
    ]);

    teamsHandle($client, $runner, $bot, $activity);

    expect($client->posted)->toBeEmpty();
    expect($runner->received())->toBeNull();
    expect(TeamsChat::query()->count())->toBe(0);
});

it('bails before the pipeline when the placeholder cannot be posted', function (): void {
    $bot = teamsHandleBot();
    $client = new FakeTeamsClient;
    $client->nextActivityId = null; // bot removed / expired secret
    $runner = new StubChannelReplyRunner('should never run');

    teamsHandle($client, $runner, $bot, teamsHandleActivity());

    expect($runner->received())->toBeNull();
    expect($client->updates)->toBeEmpty();
});

it('refuses to answer on a protected agent and creates no chat', function (): void {
    $bot = teamsHandleBot(public: false);
    $client = new FakeTeamsClient;
    $runner = new StubChannelReplyRunner('should never be sent');

    teamsHandle($client, $runner, $bot, teamsHandleActivity());

    // Gated before the pipeline: a single "unavailable" notice, no chat row.
    expect($client->posted)->toHaveCount(1)
        ->and($client->posted[0]['markdown'])->toContain('available')
        ->and($client->posted[0]['markdown'])->not->toBe('should never be sent');
    expect($client->updates)->toBeEmpty();

    expect(TeamsChat::query()->count())->toBe(0);
});
