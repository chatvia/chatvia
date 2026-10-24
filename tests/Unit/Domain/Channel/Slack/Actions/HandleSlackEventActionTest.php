<?php

use Domain\Channel\Slack\Actions\HandleSlackEventAction;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Domain\Channel\Slack\Models\SlackChannel;
use Domain\Channel\Slack\Services\SlackAdapter;
use Domain\Channel\Slack\Services\SlackChannelResolver;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Tests\Support\Channel\Slack\FakeSlackClient;
use Tests\Support\Channel\StubChannelReplyRunner;

/**
 * What the Slack handle Action adds to the shared channel turn: it strips the
 * bot's own mention before the turn runs, replies in the thread the mention
 * came from, and ignores a message that is only the mention. The streamed
 * reply itself is covered by StreamingReplyStrategyTest, the access gate by
 * ChannelAccessGateTest. The pipeline is the stub runner.
 */
function slackHandleApp(): AgentSlackApp
{
    return AgentSlackApp::factory()->create(['bot_token' => 'xoxb-secret-token']);
}

/**
 * Run one Slack message through the handle action, wired to the stubbed
 * pipeline.
 */
function slackHandle(FakeSlackClient $client, StubChannelReplyRunner $runner, AgentSlackApp $app, string $channelId, string $text, ?string $threadTs = null): void
{
    new HandleSlackEventAction($runner->handler())->execute(
        new SlackAdapter($client, new SlackChannelResolver(app(StartConversationAction::class), $client), $app, $channelId, $threadTs),
        $text,
    );
}

it('strips the bot’s own mention before the turn runs', function (): void {
    $runner = new StubChannelReplyRunner('ok');

    slackHandle(new FakeSlackClient, $runner, slackHandleApp(), 'C123CHAN', '<@U0BOT> hi there');

    expect($runner->received())->toBe('hi there');
});

it('replies in the thread the mention came from', function (): void {
    $client = new FakeSlackClient;

    slackHandle($client, new StubChannelReplyRunner('ok'), slackHandleApp(), 'C123CHAN', '<@U0BOT> hi', '1699.000001');

    expect($client->posted[0]['threadTs'])->toBe('1699.000001');
});

it('does nothing when the message is only the mention itself', function (): void {
    $client = new FakeSlackClient;
    $runner = new StubChannelReplyRunner('should never run');

    slackHandle($client, $runner, slackHandleApp(), 'C123CHAN', '<@U0BOT>');

    expect($client->posted)->toBeEmpty()
        ->and($runner->received())->toBeNull()
        ->and(SlackChannel::query()->count())->toBe(0);
});
