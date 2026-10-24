<?php

use Domain\Channel\Services\Replies\StreamingReplyStrategy;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Domain\Channel\Slack\Services\SlackAdapter;
use Domain\Channel\Slack\Services\SlackChannelResolver;
use Domain\Channel\Teams\Data\TeamsActivityData;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Teams\Services\TeamsAdapter;
use Domain\Channel\Teams\Services\TeamsChatResolver;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Tests\Support\Channel\Slack\FakeSlackClient;
use Tests\Support\Channel\StubChannelReplyRunner;
use Tests\Support\Channel\Teams\FakeTeamsClient;

/**
 * The reply on a channel whose messages can be edited (Slack, Microsoft
 * Teams): a "Thinking…" placeholder where the message came from, the reply so
 * far edited into it at most once per the platform's edit throttle and only
 * when it changed, then the final reply. The stub runner stands in for the
 * pipeline and moves the clock before each partial.
 */
function srsSlack(FakeSlackClient $client): SlackAdapter
{
    $app = AgentSlackApp::factory()->create(['bot_token' => 'xoxb-bot-token']);

    return new SlackAdapter($client, new SlackChannelResolver(app(StartConversationAction::class), $client), $app, 'C123CHAN');
}

function srsDeliver(SlackAdapter|TeamsAdapter $channel, StubChannelReplyRunner $runner): bool
{
    return new StreamingReplyStrategy($runner)->deliver($channel, $channel->resolveConversation(), 'hi there');
}

it('posts a placeholder where the message came from and edits the reply into it', function (Closure $channel, array $posted, array $updates): void {
    [$adapter, $client] = $channel();
    $runner = new StubChannelReplyRunner('Hello from the agent', ['Hello', 'Hello from']);

    expect(srsDeliver($adapter, $runner))->toBeTrue()
        ->and($runner->received())->toBe('hi there')
        ->and($client->posted)->toBe($posted)
        ->and($client->updates)->toBe($updates);
})->with([
    'Slack' => [
        function (): array {
            $client = new FakeSlackClient;

            return [srsSlack($client), $client];
        },
        [['token' => 'xoxb-bot-token', 'channelId' => 'C123CHAN', 'markdown' => '_Thinking…_', 'threadTs' => null]],
        [
            ['token' => 'xoxb-bot-token', 'channelId' => 'C123CHAN', 'ts' => '1700000000.000100', 'markdown' => 'Hello'],
            ['token' => 'xoxb-bot-token', 'channelId' => 'C123CHAN', 'ts' => '1700000000.000100', 'markdown' => 'Hello from'],
            ['token' => 'xoxb-bot-token', 'channelId' => 'C123CHAN', 'ts' => '1700000000.000100', 'markdown' => 'Hello from the agent'],
        ],
    ],
    'Microsoft Teams' => [
        function (): array {
            $client = new FakeTeamsClient;
            $activity = TeamsActivityData::fromArray([
                'type' => 'message',
                'text' => 'hi there',
                'serviceUrl' => 'https://smba.trafficmanager.net/emea/',
                'conversation' => ['id' => 'a:1conversation', 'conversationType' => 'personal'],
                'from' => ['id' => '29:user-aad', 'name' => 'Lina'],
                'recipient' => ['id' => '28:bot'],
            ]);

            return [new TeamsAdapter($client, new TeamsChatResolver(app(StartConversationAction::class)), AgentTeamsBot::factory()->create(), $activity), $client];
        },
        [['serviceUrl' => 'https://smba.trafficmanager.net/emea/', 'conversationId' => 'a:1conversation', 'markdown' => '_Thinking…_']],
        [
            ['serviceUrl' => 'https://smba.trafficmanager.net/emea/', 'conversationId' => 'a:1conversation', 'activityId' => 'act-0001', 'markdown' => 'Hello'],
            ['serviceUrl' => 'https://smba.trafficmanager.net/emea/', 'conversationId' => 'a:1conversation', 'activityId' => 'act-0001', 'markdown' => 'Hello from'],
            ['serviceUrl' => 'https://smba.trafficmanager.net/emea/', 'conversationId' => 'a:1conversation', 'activityId' => 'act-0001', 'markdown' => 'Hello from the agent'],
        ],
    ],
]);

it('skips a partial that comes sooner than the channel’s edit throttle allows', function (): void {
    // Slack takes an edit every 1.5 seconds; these partials come a second apart.
    $client = new FakeSlackClient;

    srsDeliver(srsSlack($client), new StubChannelReplyRunner('Hello from the agent', ['Hel', 'Hello', 'Hello there'], secondsBetweenPartials: 1));

    expect(array_column($client->updates, 'markdown'))->toBe(['Hello', 'Hello from the agent']);
});

it('skips a partial whose text is the same as the one it pushed last', function (): void {
    $client = new FakeSlackClient;

    srsDeliver(srsSlack($client), new StubChannelReplyRunner('Hello from the agent', ['Hello', 'Hello ', 'Hello there']));

    expect(array_column($client->updates, 'markdown'))->toBe(['Hello', 'Hello there', 'Hello from the agent']);
});

it('runs no turn when the placeholder cannot be posted', function (): void {
    $client = new FakeSlackClient;
    $client->nextTs = null;
    $runner = new StubChannelReplyRunner('Hello from the agent');

    expect(srsDeliver(srsSlack($client), $runner))->toBeFalse()
        ->and($runner->received())->toBeNull()
        ->and($client->updates)->toBe([]);
});
