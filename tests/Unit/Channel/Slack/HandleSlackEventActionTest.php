<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Slack\Actions\HandleSlackEventAction;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Domain\Channel\Slack\Models\SlackChannel;
use Domain\Channel\Slack\Services\SlackAdapter;
use Domain\Channel\Slack\Services\SlackChannelResolver;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Illuminate\Support\Str;
use Tests\Support\Channel\StubChannelReplyRunner;
use Tests\Support\Slack\FakeSlackClient;

/**
 * The handle Action wires the inbound message end to end: strip the bot's own
 *
 * @mention, resolve the channel, post a placeholder, stream the reply into it
 * via message edits, and stamp the channel. The pipeline (real LLM) is stubbed
 * here — this asserts the wiring, not the model's answer.
 */
function slackHandleApp(bool $public = true): AgentSlackApp
{
    $agent = Agent::factory()->create(['is_public' => $public]);

    return AgentSlackApp::create([
        'agent_id' => $agent->id,
        'bot_token' => 'xoxb-secret-token',
        'signing_secret' => 'signing-secret-value-12345678',
        'bot_user_id' => 'U0BOT',
        'team_name' => 'Acme Inc',
        'webhook_secret' => Str::random(48),
        'enabled' => true,
    ]);
}

/**
 * Run one Slack message through the handle action, wired to the stubbed
 * pipeline.
 */
function slackHandle(FakeSlackClient $client, StubChannelReplyRunner $runner, AgentSlackApp $app, string $channelId, string $text, ?string $threadTs = null): void
{
    (new HandleSlackEventAction($runner->handler()))->execute(
        new SlackAdapter($client, new SlackChannelResolver(app(StartConversationAction::class), $client), $app, $channelId, $threadTs),
        $text,
    );
}

it('posts a placeholder, streams partials into it, and lands the final reply', function (): void {
    $app = slackHandleApp();
    $client = new FakeSlackClient;
    $runner = new StubChannelReplyRunner('Hello from the agent', ['Hello', 'Hello from']);

    slackHandle($client, $runner, $app, 'C123CHAN', '<@U0BOT> hi there');

    // One placeholder post, then a partial edit per push + the final edit.
    expect($client->posted)->toHaveCount(1)
        ->and($client->posted[0]['token'])->toBe('xoxb-secret-token')
        ->and($client->posted[0]['channelId'])->toBe('C123CHAN')
        ->and($client->posted[0]['threadTs'])->toBeNull();

    expect($client->updates)->toHaveCount(3)
        ->and($client->updates[0]['markdown'])->toBe('Hello')
        ->and($client->updates[1]['markdown'])->toBe('Hello from')
        ->and($client->updates[2]['markdown'])->toBe('Hello from the agent')
        ->and($client->updates[2]['ts'])->toBe($client->nextTs);

    // The bot's own mention is stripped before the pipeline sees the text.
    expect($runner->received())->toBe('hi there');

    $channel = SlackChannel::query()->firstOrFail();
    expect($channel->channel_id)->toBe('C123CHAN')
        ->and($channel->last_message_at)->not->toBeNull();
});

it('replies in the thread the mention came from', function (): void {
    $app = slackHandleApp();
    $client = new FakeSlackClient;

    slackHandle($client, new StubChannelReplyRunner('ok'), $app, 'C123CHAN', '<@U0BOT> hi', '1699.000001');

    expect($client->posted[0]['threadTs'])->toBe('1699.000001');
});

it('keeps one channel mapping across multiple inbound messages', function (): void {
    $app = slackHandleApp();
    $client = new FakeSlackClient;
    $runner = new StubChannelReplyRunner('ok');

    slackHandle($client, $runner, $app, 'C900', 'first');
    slackHandle($client, $runner, $app, 'C900', 'second');

    expect(SlackChannel::query()->count())->toBe(1);
    expect($client->posted)->toHaveCount(2);
});

it('does nothing when the message is only the mention itself', function (): void {
    $app = slackHandleApp();
    $client = new FakeSlackClient;
    $runner = new StubChannelReplyRunner('should never run');

    slackHandle($client, $runner, $app, 'C123CHAN', '<@U0BOT>');

    expect($client->posted)->toBeEmpty();
    expect($runner->received())->toBeNull();
    expect(SlackChannel::query()->count())->toBe(0);
});

it('bails before the pipeline when the placeholder cannot be posted', function (): void {
    $app = slackHandleApp();
    $client = new FakeSlackClient;
    $client->nextTs = null; // kicked from the channel / missing scope
    $runner = new StubChannelReplyRunner('should never run');

    slackHandle($client, $runner, $app, 'C123CHAN', 'hello');

    expect($runner->received())->toBeNull();
    expect($client->updates)->toBeEmpty();
});

it('refuses to answer on a protected agent and creates no channel', function (): void {
    $app = slackHandleApp(public: false);
    $client = new FakeSlackClient;
    $runner = new StubChannelReplyRunner('should never be sent');

    slackHandle($client, $runner, $app, 'C123CHAN', 'hello');

    // Gated before the pipeline: a single "unavailable" notice, no channel row.
    expect($client->posted)->toHaveCount(1)
        ->and($client->posted[0]['markdown'])->toContain('available')
        ->and($client->posted[0]['markdown'])->not->toBe('should never be sent');
    expect($client->updates)->toBeEmpty();

    expect(SlackChannel::query()->count())->toBe(0);
});
