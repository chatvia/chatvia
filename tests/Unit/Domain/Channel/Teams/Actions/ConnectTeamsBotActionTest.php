<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Enums\ChannelConnectFailure;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Services\ChannelConnectionGuard;
use Domain\Channel\Teams\Actions\ConnectTeamsBotAction;
use Domain\Channel\Teams\Data\ConnectTeamsData;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Pest\Expectation;
use Tests\Support\Channel\Teams\FakeTeamsClient;

/**
 * Connect validates the Azure credentials with Entra (stubbed here) before
 * anything is stored; a reconnect updates the same row in place and rotates
 * the webhook secret so the old messaging endpoint goes dead.
 */
it('stores the bot when Entra accepts the credentials', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);

    $bot = new ConnectTeamsBotAction(new FakeTeamsClient, new ChannelConnectionGuard)
        ->execute($agent, new ConnectTeamsData('f5e1f9d2-1111-2222-3333-444455556666', 'client-secret-value'));

    expect($bot)->not->toBeNull()
        ->and($bot->agent_id)->toBe($agent->id)
        ->and($bot->app_id)->toBe('f5e1f9d2-1111-2222-3333-444455556666')
        ->and($bot->tenant_id)->toBeNull()
        ->and(strlen($bot->webhook_secret))->toBe(48)
        ->and($bot->is_enabled)->toBeTrue();
});

it('stores the tenant id for single-tenant bots', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);

    $bot = new ConnectTeamsBotAction(new FakeTeamsClient, new ChannelConnectionGuard)
        ->execute($agent, new ConnectTeamsData('f5e1f9d2-1111-2222-3333-444455556666', 'client-secret-value', 'a0b1c2d3-9999-8888-7777-666655554444'));

    expect($bot->tenant_id)->toBe('a0b1c2d3-9999-8888-7777-666655554444');
});

it('throws a rejection and stores nothing when Entra rejects the credentials', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $client = new FakeTeamsClient;
    $client->credentialsValid = false;

    expect(fn (): AgentTeamsBot => new ConnectTeamsBotAction($client, new ChannelConnectionGuard)
        ->execute($agent, new ConnectTeamsData('f5e1f9d2-1111-2222-3333-444455556666', 'wrong-secret')))
        ->toThrow(fn (ChannelConnectException $e): Expectation => expect($e->failure)->toBe(ChannelConnectFailure::Rejected));

    expect(AgentTeamsBot::query()->count())->toBe(0);
});

it('reconnecting updates the same row and rotates the webhook secret', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $action = new ConnectTeamsBotAction(new FakeTeamsClient, new ChannelConnectionGuard);

    $first = $action->execute($agent, new ConnectTeamsData('f5e1f9d2-1111-2222-3333-444455556666', 'old-secret-value'));
    $second = $action->execute($agent, new ConnectTeamsData('f5e1f9d2-1111-2222-3333-444455556666', 'new-secret-value'));

    expect(AgentTeamsBot::query()->count())->toBe(1)
        ->and($second->id)->toBe($first->id)
        ->and($second->app_secret)->toBe('new-secret-value')
        ->and($second->webhook_secret)->not->toBe($first->webhook_secret);
});

it('refuses a protected agent before calling Entra', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);

    expect(fn (): AgentTeamsBot => new ConnectTeamsBotAction(new FakeTeamsClient, new ChannelConnectionGuard)
        ->execute($agent, new ConnectTeamsData('f5e1f9d2-1111-2222-3333-444455556666', 'client-secret-value')))
        ->toThrow(fn (ChannelConnectException $e): Expectation => expect($e->failure)->toBe(ChannelConnectFailure::ProtectedAgent));

    expect(AgentTeamsBot::query()->count())->toBe(0);
});
