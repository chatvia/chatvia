<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Data\MetaPageData;
use Domain\Channel\Enums\ChannelConnectFailure;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Messenger\Actions\ConnectMessengerPageAction;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Services\ChannelConnectionGuard;
use Pest\Expectation;
use Tests\Support\Messenger\FakeMessengerClient;

/**
 * Connect is the only write in the Messenger channel that carries real
 * behaviour: it stores the OAuth-granted page token (encrypted) for the agent
 * and subscribes Chatvia's app to the page's messages so events start flowing.
 * The page id/name come straight from Facebook's `/me/accounts`, so there's no
 * token validation here — the OAuth flow already did it.
 */
it('stores the page token encrypted and subscribes the app to the page', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $client = new FakeMessengerClient;

    $page = (new ConnectMessengerPageAction($client, new ChannelConnectionGuard))->execute(
        $agent,
        new MetaPageData(id: '1234567890', name: 'Acme Support', access_token: 'EAAG-page-token-value-here'),
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
    $agent = Agent::factory()->create(['is_public' => true]);
    $client = new FakeMessengerClient;
    $action = new ConnectMessengerPageAction($client, new ChannelConnectionGuard);

    $action->execute($agent, new MetaPageData('1234567890', 'Acme Support', 'EAAG-first-token-aaaaaaaaaaaaaa'));
    $first = AgentMessengerPage::query()->firstOrFail();

    $action->execute($agent, new MetaPageData('1234567890', 'Acme Support', 'EAAG-second-token-bbbbbbbbbbbb'));

    expect(AgentMessengerPage::query()->count())->toBe(1);

    $row = AgentMessengerPage::query()->firstOrFail();
    expect($row->id)->toBe($first->id)
        ->and($row->page_access_token)->toBe('EAAG-second-token-bbbbbbbbbbbb');
});

it('refuses a protected agent and stores nothing', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);
    $client = new FakeMessengerClient;

    expect(fn (): AgentMessengerPage => (new ConnectMessengerPageAction($client, new ChannelConnectionGuard))
        ->execute($agent, new MetaPageData('1234567890', 'Acme Support', 'EAAG-page-token')))
        ->toThrow(fn (ChannelConnectException $e): Expectation => expect($e->failure)->toBe(ChannelConnectFailure::ProtectedAgent));

    expect(AgentMessengerPage::query()->count())->toBe(0)
        ->and($client->subscriptions)->toBeEmpty();
});

it('refuses a Page that already powers another agent', function (): void {
    $action = new ConnectMessengerPageAction(new FakeMessengerClient, new ChannelConnectionGuard);
    $action->execute(Agent::factory()->create(['is_public' => true]), new MetaPageData('1234567890', 'Acme Support', 'EAAG-first'));

    $other = Agent::factory()->create(['is_public' => true]);

    expect(fn (): AgentMessengerPage => $action->execute($other, new MetaPageData('1234567890', 'Acme Support', 'EAAG-second')))
        ->toThrow(fn (ChannelConnectException $e): Expectation => expect($e->failure)->toBe(ChannelConnectFailure::TakenByAnotherAgent));

    expect(AgentMessengerPage::query()->where('agent_id', $other->id)->exists())->toBeFalse();
});
