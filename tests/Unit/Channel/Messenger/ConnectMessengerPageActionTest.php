<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Messenger\Actions\ConnectMessengerPageAction;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Tests\Support\Messenger\FakeMessengerClient;

/**
 * Connect is the only write in the Messenger channel that carries real
 * behaviour: it stores the OAuth-granted page token (encrypted) for the agent
 * and subscribes Chatvia's app to the page's messages so events start flowing.
 * The page id/name come straight from Facebook's `/me/accounts`, so there's no
 * token validation here — the OAuth flow already did it.
 */
it('stores the page token encrypted and subscribes the app to the page', function (): void {
    $agent = Agent::factory()->create();
    $client = new FakeMessengerClient;

    $page = (new ConnectMessengerPageAction($client))->execute(
        $agent,
        '1234567890',
        'Acme Support',
        'EAAG-page-token-value-here',
    );

    $row = AgentMessengerPage::query()->firstOrFail();

    expect($page->is($row))->toBeTrue()
        ->and($row->agent_id)->toBe($agent->id)
        ->and($row->page_id)->toBe('1234567890')
        ->and($row->page_name)->toBe('Acme Support')
        ->and($row->enabled)->toBeTrue()
        // Encrypted cast round-trips to the original value...
        ->and($row->page_access_token)->toBe('EAAG-page-token-value-here')
        // ...but the stored ciphertext is not the plaintext.
        ->and($row->getRawOriginal('page_access_token'))->not->toBe('EAAG-page-token-value-here');

    expect($client->subscriptions)->toHaveCount(1)
        ->and($client->subscriptions[0]['pageId'])->toBe('1234567890');
});

it('reconnects in place — one page row per agent, token replaced', function (): void {
    $agent = Agent::factory()->create();
    $client = new FakeMessengerClient;
    $action = new ConnectMessengerPageAction($client);

    $action->execute($agent, '1234567890', 'Acme Support', 'EAAG-first-token-aaaaaaaaaaaaaa');
    $first = AgentMessengerPage::query()->firstOrFail();

    $action->execute($agent, '1234567890', 'Acme Support', 'EAAG-second-token-bbbbbbbbbbbb');

    expect(AgentMessengerPage::query()->count())->toBe(1);

    $row = AgentMessengerPage::query()->firstOrFail();
    expect($row->id)->toBe($first->id)
        ->and($row->page_access_token)->toBe('EAAG-second-token-bbbbbbbbbbbb');
});
