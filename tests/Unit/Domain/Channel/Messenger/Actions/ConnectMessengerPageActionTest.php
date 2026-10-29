<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Data\MetaPageData;
use Domain\Channel\Enums\ChannelConnectFailure;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Messenger\Actions\ConnectMessengerPageAction;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Services\ChannelConnectionGuard;
use Pest\Expectation;
use Tests\Support\Channel\Messenger\FakeMessengerClient;

/**
 * Connect is the only write in the Messenger channel that carries real
 * behaviour: it stores the OAuth-granted page token for the agent, one row
 * per agent replaced in place on a reconnect, and subscribes Chatvia's app to
 * the page's messages so events start flowing. The page id and name come
 * straight from Facebook's `/me/accounts`, so there's no token validation
 * here: the OAuth flow already did it. ChannelConnectionGuardTest has the
 * shared refusals.
 */
it('stores the page, subscribes the app to it, and reconnects in place', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $client = new FakeMessengerClient;
    $action = new ConnectMessengerPageAction($client, new ChannelConnectionGuard);

    $page = $action->execute($agent, new MetaPageData(id: '1234567890', name: 'Acme Support', access_token: 'EAAG-first-token-aaaaaaaaaaaaaa'));
    $action->execute($agent, new MetaPageData('1234567890', 'Acme Support', 'EAAG-second-token-bbbbbbbbbbbb'));

    $row = AgentMessengerPage::query()->sole();
    expect($row->id)->toBe($page->id)
        ->and($row->agent_id)->toBe($agent->id)
        ->and($row->page_id)->toBe('1234567890')
        ->and($row->page_name)->toBe('Acme Support')
        ->and($row->page_access_token)->toBe('EAAG-second-token-bbbbbbbbbbbb')
        ->and($row->is_enabled)->toBeTrue()
        ->and($client->subscriptions[0]['pageId'])->toBe('1234567890');
});

it('refuses a protected agent and stores nothing', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);
    $client = new FakeMessengerClient;

    expect(fn (): AgentMessengerPage => new ConnectMessengerPageAction($client, new ChannelConnectionGuard)
        ->execute($agent, new MetaPageData('1234567890', 'Acme Support', 'EAAG-page-token')))
        ->toThrow(fn (ChannelConnectException $e): Expectation => expect($e->failure)->toBe(ChannelConnectFailure::ProtectedAgent));

    expect(AgentMessengerPage::query()->count())->toBe(0)
        ->and($client->subscriptions)->toBeEmpty();
});
