<?php

use Domain\Channel\Slack\Services\SlackClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * The client's requests are the contract with the Slack Web API, which the
 * Fake-based tests never reach: each method is a JSON POST with the bot
 * token, replies are sent as Slack mrkdwn, and an `ok: false` answer counts
 * as a failure even though Slack sends it with HTTP 200.
 */
it('posts the reply as mrkdwn in the thread and returns its ts', function (): void {
    Http::fake(['slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '1700000000.000200'])]);

    $ts = new SlackClient()->postMessage('xoxb-token', 'C123CHAN', 'Hello **there**', '1699.000001');

    expect($ts)->toBe('1700000000.000200');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://slack.com/api/chat.postMessage'
        && $request->hasHeader('Authorization', 'Bearer xoxb-token')
        && $request->isJson()
        && $request->data() === ['channel' => 'C123CHAN', 'text' => 'Hello *there*', 'thread_ts' => '1699.000001']);
});

it('leaves thread_ts out of a reply that is not in a thread', function (): void {
    Http::fake(['slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '1700000000.000200'])]);

    new SlackClient()->postMessage('xoxb-token', 'C123CHAN', 'Hello');

    Http::assertSent(fn (Request $request): bool => $request->data() === ['channel' => 'C123CHAN', 'text' => 'Hello']);
});

it('rewrites a posted message with chat.update', function (): void {
    Http::fake(['slack.com/api/chat.update' => Http::response(['ok' => true])]);

    expect(new SlackClient()->updateMessage('xoxb-token', 'C123CHAN', '1700000000.000200', 'Hello **there**'))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://slack.com/api/chat.update'
        && $request->data() === ['channel' => 'C123CHAN', 'ts' => '1700000000.000200', 'text' => 'Hello *there*']);
});

it('sends a method without arguments as an empty JSON object', function (): void {
    Http::fake(['slack.com/api/auth.test' => Http::response(['ok' => true, 'team' => 'Acme Inc', 'user_id' => 'U0BOT'])]);

    expect(new SlackClient()->authTest('xoxb-token'))->toBe(['team' => 'Acme Inc', 'user_id' => 'U0BOT']);

    Http::assertSent(fn (Request $request): bool => $request->body() === '{}');
});

it('treats an ok: false answer as a failure', function (): void {
    Http::fake(['slack.com/api/*' => Http::response(['ok' => false, 'error' => 'invalid_auth'])]);
    $client = new SlackClient;

    expect($client->authTest('xoxb-bad'))->toBeNull()
        ->and($client->postMessage('xoxb-bad', 'C123CHAN', 'Hello'))->toBeNull()
        ->and($client->updateMessage('xoxb-bad', 'C123CHAN', '1700000000.000200', 'Hello'))->toBeFalse();
});
