<?php

use Domain\Channel\Teams\Actions\HandleTeamsActivityAction;
use Domain\Channel\Teams\Data\TeamsActivityData;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Teams\Models\TeamsChat;
use Domain\Channel\Teams\Services\TeamsAdapter;
use Domain\Channel\Teams\Services\TeamsChatResolver;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Illuminate\Support\Carbon;
use Tests\Support\Channel\StubChannelReplyRunner;
use Tests\Support\Teams\FakeTeamsClient;

/**
 * The handle Action wires the inbound activity end to end: strip the bot's
 * own @mention, resolve the chat, post a placeholder, stream the reply into
 * it via activity edits, and stamp the chat. The pipeline (real LLM) is
 * stubbed here — this asserts the wiring, not the model's answer.
 */
function teamsHandleBot(): AgentTeamsBot
{
    // The app id the activity's recipient names.
    return AgentTeamsBot::factory()->create(['app_id' => 'f5e1f9d2-1111-2222-3333-444455556666']);
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

    $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));

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

    // Made at 10:00, stamped when the reply landed two partials (a minute each) later.
    $chat = TeamsChat::query()->firstOrFail();
    expect($chat->conversation_id)->toBe('a:1conversation')
        ->and($chat->last_message_at->toDateTimeString())->toBe('2026-10-01 10:02:00');
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
