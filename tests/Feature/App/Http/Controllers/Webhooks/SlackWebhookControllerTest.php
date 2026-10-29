<?php

use Domain\Channel\Slack\Jobs\HandleSlackEventJob;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * The webhook is public and unauthenticated, so its security is the test
 * surface: the {secret} segment routes to the agent and the request must carry
 * a valid Slack HMAC signature (signed secrets, ±5 min replay window); events
 * are processed once; bot/self messages never dispatch (or the bot would
 * answer itself in a loop); and it always answers 200.
 */
beforeEach(function (): void {
    Bus::fake();
    Cache::flush();
    $this->secret = Str::random(48);
    $this->signingSecret = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4';
    $this->slackApp = AgentSlackApp::factory()->create(['signing_secret' => $this->signingSecret, 'webhook_secret' => $this->secret]);
});

/**
 * @param  array<string, mixed>  $event
 * @return array<string, mixed>
 */
function slackEnvelope(array $event, string $eventId = 'Ev0001'): array
{
    return [
        'type' => 'event_callback',
        'event_id' => $eventId,
        'event' => $event,
    ];
}

/**
 * @return array<string, mixed>
 */
function slackMention(string $text = '<@U0BOT> hello'): array
{
    return [
        'type' => 'app_mention',
        'user' => 'U123USER',
        'text' => $text,
        'channel' => 'C123CHAN',
        'ts' => '1700000000.000100',
    ];
}

/**
 * @param  array<string, mixed>  $payload
 */
function postSlackEvent(string $urlSecret, array $payload, ?string $signWith, ?int $timestamp = null): TestResponse
{
    $headers = [];

    if ($signWith !== null) {
        $timestamp ??= now()->getTimestamp();
        $body = json_encode($payload);
        $headers = [
            'X-Slack-Request-Timestamp' => (string) $timestamp,
            'X-Slack-Signature' => 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$body}", $signWith),
        ];
    }

    return test()->postJson(route('slack.webhook', ['secret' => $urlSecret]), $payload, $headers);
}

it('dispatches a job for a valid, signed mention, once per event_id', function (): void {
    postSlackEvent($this->secret, slackEnvelope(slackMention(), eventId: 'Ev7'), $this->signingSecret)
        ->assertOk()
        ->assertJson(['ok' => true]);
    postSlackEvent($this->secret, slackEnvelope(slackMention(), eventId: 'Ev7'), $this->signingSecret)->assertOk();

    Bus::assertDispatchedTimes(HandleSlackEventJob::class, 1);
    Bus::assertDispatched(HandleSlackEventJob::class, fn (HandleSlackEventJob $job): bool => $job->channelId === 'C123CHAN' && $job->text === '<@U0BOT> hello' && $job->app->is($this->slackApp) && jobQueue($job) === 'messages');
});

it('ignores a request the app did not sign just now', function (Closure $signWith, int $secondsAgo): void {
    postSlackEvent($this->secret, slackEnvelope(slackMention()), $signWith->call($this), now()->getTimestamp() - $secondsAgo)->assertOk();

    Bus::assertNotDispatched(HandleSlackEventJob::class);
})->with([
    'a bad signature' => [fn (): string => 'wrong-signing-secret-000000000000', 0],
    'no signature at all' => [fn (): null => null, 0],
    'a signed but stale request (replay protection)' => [fn (): string => $this->signingSecret, 600],
]);

it('answers 200 without dispatching for an unknown or disabled app, or a bot’s own message', function (): void {
    $own = slackMention() + ['bot_id' => 'B0SELF'];
    postSlackEvent($this->secret, slackEnvelope($own, eventId: 'Ev10'), $this->signingSecret)->assertOk();

    $self = array_merge(slackMention(), ['user' => 'U0BOT']);
    postSlackEvent($this->secret, slackEnvelope($self, eventId: 'Ev11'), $this->signingSecret)->assertOk();

    postSlackEvent(Str::random(48), slackEnvelope(slackMention(), eventId: 'Ev12'), $this->signingSecret)->assertOk();

    $this->slackApp->update(['is_enabled' => false]);
    postSlackEvent($this->secret, slackEnvelope(slackMention(), eventId: 'Ev13'), $this->signingSecret)->assertOk();

    Bus::assertNotDispatched(HandleSlackEventJob::class);
});
