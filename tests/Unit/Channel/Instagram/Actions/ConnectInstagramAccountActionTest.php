<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Enums\ChannelConnectFailure;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Instagram\Actions\ConnectInstagramAccountAction;
use Domain\Channel\Instagram\Data\InstagramAccountData;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Services\ChannelConnectionGuard;
use Pest\Expectation;
use Tests\Support\Instagram\FakeInstagramClient;

/**
 * Connect stores the OAuth-granted Page token + IG account ids for
 * the agent and best-effort subscribes the linked Page to its `messages` field
 * so DMs start flowing. The ids come straight from Facebook's /me/accounts →
 * instagram_business_account (already valid), so there's no token validation.
 */
function instagramConnectData(string $pageToken, string $igId = '17840012345'): InstagramAccountData
{
    return new InstagramAccountData(
        page_id: '998877',
        page_name: 'Acme',
        page_access_token: $pageToken,
        ig_id: $igId,
        ig_username: 'acme.support',
    );
}

it('stores the IG account and subscribes the linked page with its token', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $client = new FakeInstagramClient;

    $account = new ConnectInstagramAccountAction($client, new ChannelConnectionGuard)->execute($agent, instagramConnectData('EAAG-page-token-value-here'));

    $row = AgentInstagramAccount::query()->firstOrFail();

    expect($account->is($row))->toBeTrue()
        ->and($row->agent_id)->toBe($agent->id)
        ->and($row->ig_id)->toBe('17840012345')
        ->and($row->username)->toBe('acme.support')
        ->and($row->page_id)->toBe('998877')
        ->and($row->is_enabled)->toBeTrue();

    expect($client->subscriptions)->toHaveCount(1)
        ->and($client->subscriptions[0]['pageId'])->toBe('998877')
        ->and($client->subscriptions[0]['pageToken'])->toBe('EAAG-page-token-value-here');
});

it('reconnects in place — one account row per agent, page token replaced', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $action = new ConnectInstagramAccountAction(new FakeInstagramClient, new ChannelConnectionGuard);

    $action->execute($agent, instagramConnectData('EAAG-first-token'));
    $first = AgentInstagramAccount::query()->firstOrFail();

    $action->execute($agent, instagramConnectData('EAAG-second-token'));

    expect(AgentInstagramAccount::query()->count())->toBe(1);

    $row = AgentInstagramAccount::query()->firstOrFail();
    expect($row->id)->toBe($first->id)
        ->and($row->page_access_token)->toBe('EAAG-second-token');
});

it('refuses a protected agent and stores nothing', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);

    expect(fn (): AgentInstagramAccount => new ConnectInstagramAccountAction(new FakeInstagramClient, new ChannelConnectionGuard)
        ->execute($agent, instagramConnectData('EAAG-token')))
        ->toThrow(fn (ChannelConnectException $e): Expectation => expect($e->failure)->toBe(ChannelConnectFailure::ProtectedAgent));

    expect(AgentInstagramAccount::query()->count())->toBe(0);
});

it('refuses an account that already powers another agent', function (): void {
    $action = new ConnectInstagramAccountAction(new FakeInstagramClient, new ChannelConnectionGuard);
    $action->execute(Agent::factory()->create(['is_public' => true]), instagramConnectData('EAAG-first'));

    $other = Agent::factory()->create(['is_public' => true]);

    expect(fn (): AgentInstagramAccount => $action->execute($other, instagramConnectData('EAAG-second')))
        ->toThrow(fn (ChannelConnectException $e): Expectation => expect($e->failure)->toBe(ChannelConnectFailure::TakenByAnotherAgent));

    expect(AgentInstagramAccount::query()->where('agent_id', $other->id)->exists())->toBeFalse();
});
