<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Instagram\Actions\ConnectInstagramAccountAction;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Tests\Support\Instagram\FakeInstagramClient;

/**
 * Connect stores the OAuth-granted Page token + IG account ids (encrypted) for
 * the agent and best-effort subscribes the linked Page to its `messages` field
 * so DMs start flowing. The ids come straight from Facebook's /me/accounts →
 * instagram_business_account (already valid), so there's no token validation.
 */
it('stores the IG account + page token encrypted and subscribes the linked page', function (): void {
    $agent = Agent::factory()->create();
    $client = new FakeInstagramClient;

    $account = (new ConnectInstagramAccountAction($client))->execute(
        $agent,
        '17840012345',
        'acme.support',
        '998877',
        'EAAG-page-token-value-here',
    );

    $row = AgentInstagramAccount::query()->firstOrFail();

    expect($account->is($row))->toBeTrue()
        ->and($row->agent_id)->toBe($agent->id)
        ->and($row->ig_id)->toBe('17840012345')
        ->and($row->username)->toBe('acme.support')
        ->and($row->page_id)->toBe('998877')
        ->and($row->enabled)->toBeTrue()
        // Encrypted cast round-trips...
        ->and($row->page_access_token)->toBe('EAAG-page-token-value-here')
        // ...but the stored ciphertext is not the plaintext.
        ->and($row->getRawOriginal('page_access_token'))->not->toBe('EAAG-page-token-value-here');

    expect($client->subscriptions)->toHaveCount(1)
        ->and($client->subscriptions[0]['pageId'])->toBe('998877')
        ->and($client->subscriptions[0]['pageToken'])->toBe('EAAG-page-token-value-here');
});

it('reconnects in place — one account row per agent, page token replaced', function (): void {
    $agent = Agent::factory()->create();
    $action = new ConnectInstagramAccountAction(new FakeInstagramClient);

    $action->execute($agent, '17840012345', 'acme.support', '998877', 'EAAG-first-token');
    $first = AgentInstagramAccount::query()->firstOrFail();

    $action->execute($agent, '17840012345', 'acme.support', '998877', 'EAAG-second-token');

    expect(AgentInstagramAccount::query()->count())->toBe(1);

    $row = AgentInstagramAccount::query()->firstOrFail();
    expect($row->id)->toBe($first->id)
        ->and($row->page_access_token)->toBe('EAAG-second-token');
});
