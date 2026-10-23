<?php

use Domain\Channel\Messenger\Services\MessengerClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * The client's requests are the contract with the Messenger Send API, which
 * the Fake-based tests never reach: replies go out as plain text in the Send
 * API shape, split at Messenger's 2000-character limit, with the Page token
 * as a bearer token on the configured Graph version.
 */
beforeEach(function (): void {
    config(['services.meta.graph_version' => 'v25.0']);
});

it('sends a reply as plain text in the Send API shape', function (): void {
    Http::fake(['graph.facebook.com/v25.0/me/messages' => Http::response(['message_id' => 'mid.1'])]);

    new MessengerClient()->sendMessage('EAAG-page-token', '24680', 'Hello **there**');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://graph.facebook.com/v25.0/me/messages'
        && $request->hasHeader('Authorization', 'Bearer EAAG-page-token')
        && $request->data() === [
            'recipient' => ['id' => '24680'],
            'messaging_type' => 'RESPONSE',
            'message' => ['text' => 'Hello there'],
        ]);
});

it('splits a reply over 2000 characters and stops after a part fails', function (): void {
    Http::fake(['graph.facebook.com/v25.0/me/messages' => Http::sequence()
        ->push(['message_id' => 'mid.1'])
        ->push(['error' => ['message' => 'Rate limited']], 400)
        ->push(['message_id' => 'mid.3'])]);

    new MessengerClient()->sendMessage('EAAG-page-token', '24680', str_repeat('a', 4500));

    $parts = array_map(fn (array $pair): int => mb_strlen($pair[0]['message']['text']), Http::recorded()->all());
    expect($parts)->toBe([2000, 2000]);
});

it('shows the typing indicator with the typing_on sender action', function (): void {
    Http::fake(['graph.facebook.com/v25.0/me/messages' => Http::response(['recipient_id' => '24680'])]);

    new MessengerClient()->sendTypingAction('EAAG-page-token', '24680');

    Http::assertSent(fn (Request $request): bool => $request->data() === ['recipient' => ['id' => '24680'], 'sender_action' => 'typing_on']);
});

it('subscribes the app to the Page’s messages field', function (): void {
    Http::fake(['graph.facebook.com/v25.0/1234567890/subscribed_apps' => Http::response(['success' => true])]);

    expect(new MessengerClient()->subscribePage('EAAG-page-token', '1234567890'))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->data() === ['subscribed_fields' => 'messages']);
});

it('reads the Page identity, and treats a Graph error body as a failure', function (): void {
    Http::fake(['graph.facebook.com/v25.0/me*' => Http::sequence()
        ->push(['id' => '1234567890', 'name' => 'Acme Support'])
        ->push(['error' => ['message' => 'Invalid OAuth access token']])]);
    $client = new MessengerClient;

    expect($client->pageInfo('EAAG-page-token'))->toBe(['id' => '1234567890', 'name' => 'Acme Support'])
        ->and($client->pageInfo('EAAG-bad-token'))->toBeNull();
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://graph.facebook.com/v25.0/me?fields=id%2Cname');
});
