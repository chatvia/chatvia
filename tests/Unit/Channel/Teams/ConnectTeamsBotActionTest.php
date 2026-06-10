<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Teams\Actions\ConnectTeamsBotAction;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Tests\Support\Teams\FakeTeamsClient;

/**
 * Connect validates the Azure credentials with Entra (stubbed here) before
 * anything is stored; a reconnect updates the same row in place and rotates
 * the webhook secret so the old messaging endpoint goes dead.
 */
it('stores the bot when Entra accepts the credentials', function (): void {
    $agent = Agent::factory()->create();

    $bot = (new ConnectTeamsBotAction(new FakeTeamsClient))
        ->execute($agent, 'f5e1f9d2-1111-2222-3333-444455556666', 'client-secret-value', null);

    expect($bot)->not->toBeNull()
        ->and($bot->agent_id)->toBe($agent->id)
        ->and($bot->app_id)->toBe('f5e1f9d2-1111-2222-3333-444455556666')
        ->and($bot->app_secret)->toBe('client-secret-value') // decrypted by the cast
        ->and($bot->tenant_id)->toBeNull()
        ->and(strlen($bot->webhook_secret))->toBe(48)
        ->and($bot->enabled)->toBeTrue();
});

it('stores the tenant id for single-tenant bots', function (): void {
    $agent = Agent::factory()->create();

    $bot = (new ConnectTeamsBotAction(new FakeTeamsClient))
        ->execute($agent, 'f5e1f9d2-1111-2222-3333-444455556666', 'client-secret-value', 'a0b1c2d3-9999-8888-7777-666655554444');

    expect($bot->tenant_id)->toBe('a0b1c2d3-9999-8888-7777-666655554444');
});

it('returns null and stores nothing when Entra rejects the credentials', function (): void {
    $agent = Agent::factory()->create();
    $client = new FakeTeamsClient;
    $client->credentialsValid = false;

    $bot = (new ConnectTeamsBotAction($client))
        ->execute($agent, 'f5e1f9d2-1111-2222-3333-444455556666', 'wrong-secret', null);

    expect($bot)->toBeNull();
    expect(AgentTeamsBot::query()->count())->toBe(0);
});

it('reconnecting updates the same row and rotates the webhook secret', function (): void {
    $agent = Agent::factory()->create();
    $action = new ConnectTeamsBotAction(new FakeTeamsClient);

    $first = $action->execute($agent, 'f5e1f9d2-1111-2222-3333-444455556666', 'old-secret-value', null);
    $second = $action->execute($agent, 'f5e1f9d2-1111-2222-3333-444455556666', 'new-secret-value', null);

    expect(AgentTeamsBot::query()->count())->toBe(1)
        ->and($second->id)->toBe($first->id)
        ->and($second->app_secret)->toBe('new-secret-value')
        ->and($second->webhook_secret)->not->toBe($first->webhook_secret);
});
