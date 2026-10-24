<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Enums\ChannelConnectFailure;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Services\ChannelConnectionGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Pest\Expectation;

/**
 * The rules every channel connect action shares: channels serve public agents
 * only, and one account powers at most one agent. Only an enabled row holds an
 * account, so the switched-off row a deleted agent leaves behind does not
 * block connecting it again. When two agents race for the same account, the
 * unique index catches the loser, which gets the same "taken" failure as the
 * up-front check instead of a server error.
 */
function ccgPage(Agent $agent, bool $enabled = true): AgentMessengerPage
{
    return AgentMessengerPage::factory()->forAgent($agent)->create(['page_id' => 'PAGE-1', 'is_enabled' => $enabled]);
}

/** @return Builder<AgentMessengerPage> */
function ccgSamePage(): Builder
{
    return AgentMessengerPage::query()->where('page_id', 'PAGE-1');
}

it('refuses a protected agent and lets a public one through', function (): void {
    $guard = new ChannelConnectionGuard;

    expect(fn () => $guard->ensureAgentIsPublic(Agent::factory()->create(['is_public' => false])))
        ->toThrow(fn (ChannelConnectException $e): Expectation => expect($e->failure)->toBe(ChannelConnectFailure::ProtectedAgent))
        ->and(fn () => $guard->ensureAgentIsPublic(Agent::factory()->create(['is_public' => true])))
        ->not->toThrow(ChannelConnectException::class);
});

it('refuses an account another agent holds', function (): void {
    ccgPage(Agent::factory()->create());

    expect(fn () => new ChannelConnectionGuard()->ensureAccountIsFree(Agent::factory()->create(), ccgSamePage()))
        ->toThrow(fn (ChannelConnectException $e): Expectation => expect($e->failure)->toBe(ChannelConnectFailure::TakenByAnotherAgent));
});

it('lets an agent connect an account no other agent’s enabled row holds', function (Closure $holder): void {
    $agent = Agent::factory()->create();
    $holder($agent);

    expect(fn () => new ChannelConnectionGuard()->ensureAccountIsFree($agent, ccgSamePage()))
        ->not->toThrow(ChannelConnectException::class);
})->with([
    'the agent’s own row' => [fn (Agent $agent): AgentMessengerPage => ccgPage($agent)],
    'the switched-off row of a deleted agent' => [fn (): AgentMessengerPage => ccgPage(tap(Agent::factory()->create())->delete(), enabled: false)],
]);

it('turns a unique-index clash into a taken-by-another-agent failure', function (): void {
    ccgPage(Agent::factory()->create());
    $second = Agent::factory()->create();

    expect(fn (): Model => new ChannelConnectionGuard()->store(fn (): AgentMessengerPage => ccgPage($second)))
        ->toThrow(fn (ChannelConnectException $e): Expectation => expect($e->failure)->toBe(ChannelConnectFailure::TakenByAnotherAgent));
});
