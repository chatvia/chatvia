<?php

use Domain\Channel\Instagram\Services\InstagramClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The client's requests are the contract with Instagram Messaging through the
 * linked Facebook Page, which the Fake-based tests never reach: replies go
 * out as plain text in the Send API shape, split at Instagram's
 * 1000-character limit, with the Page token as a bearer token.
 */
beforeEach(function (): void {
    config(['services.meta.graph_version' => 'v25.0']);
});

it('sends a reply as plain text in parts of at most 1000 characters', function (): void {
    Http::fake(['graph.facebook.com/v25.0/me/messages' => Http::response(['message_id' => 'mid.1'])]);

    new InstagramClient()->sendMessage('EAAG-page-token', '5550001234', '**Hi** '.Str::repeat('a', 1500));

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://graph.facebook.com/v25.0/me/messages'
        && $request->hasHeader('Authorization', 'Bearer EAAG-page-token')
        && $request['recipient'] === ['id' => '5550001234']
        && $request['messaging_type'] === 'RESPONSE'
        && $request['message']['text'] === 'Hi '.Str::repeat('a', 997));
});

it('shows the typing indicator with the typing_on sender action', function (): void {
    Http::fake(['graph.facebook.com/v25.0/me/messages' => Http::response(['recipient_id' => '5550001234'])]);

    new InstagramClient()->sendTypingAction('EAAG-page-token', '5550001234');

    Http::assertSent(fn (Request $request): bool => $request->data() === ['recipient' => ['id' => '5550001234'], 'sender_action' => 'typing_on']);
});

it('subscribes the linked Page to its messages field', function (): void {
    Http::fake(['graph.facebook.com/v25.0/998877/subscribed_apps' => Http::response(['success' => true])]);

    expect(new InstagramClient()->subscribeAccount('EAAG-page-token', '998877'))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->data() === ['subscribed_fields' => 'messages']);
});

it('looks the sender up by name and username, and returns null when Meta refuses', function (): void {
    Http::fake(['graph.facebook.com/v25.0/5550001234*' => Http::sequence()
        ->push(['name' => 'Sam', 'username' => 'sam.codes'])
        ->push(['error' => ['message' => 'Unsupported get request']], 400)]);
    $client = new InstagramClient;

    expect($client->userProfile('EAAG-page-token', '5550001234'))->toBe(['name' => 'Sam', 'username' => 'sam.codes'])
        ->and($client->userProfile('EAAG-page-token', '5550001234'))->toBeNull();
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://graph.facebook.com/v25.0/5550001234?fields=name%2Cusername');
});
