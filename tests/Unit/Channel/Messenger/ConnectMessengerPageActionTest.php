<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Messenger\Actions\ConnectMessengerPageAction;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Tests\Support\Messenger\FakeMessengerClient;

/**
 * Connect is the only write in the Messenger channel that carries real
 * behaviour: it validates the page token with the Graph API, stores it (and
 * the app secret) encrypted with a fresh webhook secret, and subscribes the
 * app to the page's messages — and must reject a bad token without writing.
 */
it('connects a page, stores its secrets encrypted, and subscribes the app to the page', function (): void {
    $agent = Agent::factory()->create();
    $client = new FakeMessengerClient;

    $page = (new ConnectMessengerPageAction($client))->execute($agent, 'EAAG-page-token-value-here', 'app-secret-value-1234567890');

    expect($page)->not->toBeNull();

    $row = AgentMessengerPage::query()->firstOrFail();

    expect($row->agent_id)->toBe($agent->id)
        ->and($row->page_id)->toBe('1234567890')
        ->and($row->page_name)->toBe('Acme Support')
        ->and($row->enabled)->toBeTrue()
        ->and(strlen($row->webhook_secret))->toBe(48)
        // Encrypted casts round-trip to the original values.
        ->and($row->page_access_token)->toBe('EAAG-page-token-value-here')
        ->and($row->app_secret)->toBe('app-secret-value-1234567890')
        // The stored ciphertext is not the plaintext.
        ->and($row->getRawOriginal('page_access_token'))->not->toBe('EAAG-page-token-value-here')
        ->and($row->getRawOriginal('app_secret'))->not->toBe('app-secret-value-1234567890');

    expect($client->subscriptions)->toHaveCount(1)
        ->and($client->subscriptions[0]['pageId'])->toBe('1234567890');
});

it('returns null and stores nothing when Meta rejects the token', function (): void {
    $agent = Agent::factory()->create();
    $client = new FakeMessengerClient;
    $client->page = null; // Meta: invalid token.

    $page = (new ConnectMessengerPageAction($client))->execute($agent, 'EAAG-bogus-token-value-here', 'app-secret-value-1234567890');

    expect($page)->toBeNull();
    expect(AgentMessengerPage::query()->count())->toBe(0);
    expect($client->subscriptions)->toBeEmpty();
});

it('reconnects in place — one page row per agent, secrets rotated', function (): void {
    $agent = Agent::factory()->create();
    $client = new FakeMessengerClient;
    $action = new ConnectMessengerPageAction($client);

    $action->execute($agent, 'EAAG-first-token-aaaaaaaaaaaaaa', 'first-app-secret-aaaaaaaaaaaa');
    $first = AgentMessengerPage::query()->firstOrFail();

    $action->execute($agent, 'EAAG-second-token-bbbbbbbbbbbb', 'second-app-secret-bbbbbbbbbb');

    expect(AgentMessengerPage::query()->count())->toBe(1);

    $row = AgentMessengerPage::query()->firstOrFail();
    expect($row->id)->toBe($first->id)
        ->and($row->page_access_token)->toBe('EAAG-second-token-bbbbbbbbbbbb')
        ->and($row->app_secret)->toBe('second-app-secret-bbbbbbbbbb')
        ->and($row->webhook_secret)->not->toBe($first->webhook_secret);
});
