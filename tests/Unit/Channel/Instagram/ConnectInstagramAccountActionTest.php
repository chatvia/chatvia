<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Instagram\Actions\ConnectInstagramAccountAction;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Tests\Support\Instagram\FakeInstagramClient;

/**
 * Connect validates the access token with the Graph API (stubbed here) before
 * anything is stored, subscribes the app to the account's `messages` field,
 * and a reconnect updates the same row in place and rotates the webhook
 * secret so the old Callback URL goes dead.
 */
it('stores the account and subscribes the app when the token is valid', function (): void {
    $agent = Agent::factory()->create();
    $client = new FakeInstagramClient;

    $account = (new ConnectInstagramAccountAction($client))
        ->execute($agent, 'IGAA-token-value-1234567890', 'app-secret-value-1234567890');

    expect($account)->not->toBeNull()
        ->and($account->agent_id)->toBe($agent->id)
        ->and($account->access_token)->toBe('IGAA-token-value-1234567890') // decrypted by the cast
        ->and($account->app_secret)->toBe('app-secret-value-1234567890')
        ->and($account->ig_id)->toBe('17840012345')
        ->and($account->username)->toBe('acme.support')
        ->and(strlen($account->webhook_secret))->toBe(48)
        ->and($account->enabled)->toBeTrue();

    // The app is subscribed to the account's messages field (best-effort).
    expect($client->subscriptions)->toBe(['IGAA-token-value-1234567890']);
});

it('returns null and stores nothing when the token is rejected', function (): void {
    $agent = Agent::factory()->create();
    $client = new FakeInstagramClient;
    $client->account = null;

    $account = (new ConnectInstagramAccountAction($client))
        ->execute($agent, 'bad-token-value-1234567890', 'app-secret-value-1234567890');

    expect($account)->toBeNull();
    expect(AgentInstagramAccount::query()->count())->toBe(0);
    expect($client->subscriptions)->toBeEmpty();
});

it('reconnecting updates the same row and rotates the webhook secret', function (): void {
    $agent = Agent::factory()->create();
    $action = new ConnectInstagramAccountAction(new FakeInstagramClient);

    $first = $action->execute($agent, 'IGAA-old-token-1234567890', 'app-secret-value-1234567890');
    $second = $action->execute($agent, 'IGAA-new-token-1234567890', 'app-secret-value-1234567890');

    expect(AgentInstagramAccount::query()->count())->toBe(1)
        ->and($second->id)->toBe($first->id)
        ->and($second->access_token)->toBe('IGAA-new-token-1234567890')
        ->and($second->webhook_secret)->not->toBe($first->webhook_secret);
});
