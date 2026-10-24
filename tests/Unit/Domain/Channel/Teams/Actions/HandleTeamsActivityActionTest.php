<?php

use Domain\Channel\Teams\Actions\HandleTeamsActivityAction;
use Domain\Channel\Teams\Data\TeamsActivityData;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Teams\Models\TeamsChat;
use Domain\Channel\Teams\Services\TeamsAdapter;
use Domain\Channel\Teams\Services\TeamsChatResolver;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Tests\Support\Channel\StubChannelReplyRunner;
use Tests\Support\Channel\Teams\FakeTeamsClient;

/**
 * What the Teams handle Action adds to the shared channel turn: it strips the
 * bot's own @mention (found through the mention entities), unwraps other
 * people's mentions, and ignores a message that is only the mention. The
 * streamed reply itself is covered by StreamingReplyStrategyTest. The
 * pipeline is the stub runner.
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

    expect($client->posted)->toBeEmpty()
        ->and($runner->received())->toBeNull()
        ->and(TeamsChat::query()->count())->toBe(0);
});
