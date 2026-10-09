<?php

use Domain\Channel\Whatsapp\Services\WhatsappClient;
use Illuminate\Support\Facades\Http;

/**
 * The client's outbound payloads are the contract with Meta — a wrong field
 * name fails silently in the Fake-based tests, so these hit the (faked) HTTP
 * layer and assert the documented Cloud API shapes verbatim.
 */
beforeEach(function (): void {
    $this->client = new WhatsappClient;
    // Stub order matters: Http::fake's first matching stub wins, so each test
    // registers its own (most need the generic happy-path send response).
    $this->fakeSend = fn () => Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT']]])]);
});

it('sends a text message with the documented Cloud API payload', function (): void {
    ($this->fakeSend)();

    $this->client->sendMessage('token-x', '111222333', '4915551234567', 'Hello **there**');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/111222333/messages')
        && $request->hasHeader('Authorization', 'Bearer token-x')
        && $request['messaging_product'] === 'whatsapp'
        && $request['recipient_type'] === 'individual'
        && $request['to'] === '4915551234567'
        && $request['type'] === 'text'
        && $request['text']['preview_url'] === true
        // formatted to WhatsApp markup, not CommonMark
        && $request['text']['body'] === 'Hello *there*');
});

it('chunks a body over the 4096-char limit into multiple sends', function (): void {
    ($this->fakeSend)();

    $this->client->sendMessage('token-x', '111222333', '4915551234567', str_repeat('a', 5000));

    $bodies = [];
    Http::assertSentCount(2);
    Http::recorded(function ($request) use (&$bodies): true {
        $bodies[] = $request['text']['body'];

        return true;
    });

    expect(mb_strlen($bodies[0]))->toBe(4096)
        ->and(mb_strlen($bodies[1]))->toBe(904);
});

it('marks read and shows typing off the inbound wamid in one call', function (): void {
    ($this->fakeSend)();

    $this->client->sendTypingIndicator('token-x', '111222333', 'wamid.IN9');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/111222333/messages')
        && $request['messaging_product'] === 'whatsapp'
        && $request['status'] === 'read'
        && $request['message_id'] === 'wamid.IN9'
        && $request['typing_indicator']['type'] === 'text');
});

it('validates credentials via the phone number node', function (): void {
    Http::fake(['graph.facebook.com/*' => Http::response(['id' => '111222333', 'display_phone_number' => '+1 555-123-4567', 'verified_name' => 'Acme'])]);

    $info = $this->client->numberInfo('token-x', '111222333');

    expect($info)->toBe(['display_phone_number' => '+1 555-123-4567', 'verified_name' => 'Acme']);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/111222333')
        && str_contains($request->url(), 'display_phone_number%2Cverified_name'));
});

it('returns null when Meta rejects the credentials', function (): void {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 401)]);

    expect($this->client->numberInfo('bad-token', '111222333'))->toBeNull();
});
