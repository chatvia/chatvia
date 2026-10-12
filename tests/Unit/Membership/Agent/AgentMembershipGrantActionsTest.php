<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\GrantAgentMembershipAction;
use Domain\Membership\Agent\Actions\RevokeAgentMembershipAction;

/**
 * Grant/Revoke share the agent_chatter pivot toggle across the agent-scoped
 * Members tab and the workspace Members settings, so the idempotent
 * attach/detach behaviour is pinned here, as is the rule that a chatter is
 * only ever granted agents of its own workspace.
 */
function membershipFixtures(): array
{
    $workspace = User::factory()->create();
    $agent = Agent::factory()->create(['workspace_id' => $workspace->workspace()->id]);
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create();

    return [$agent, $chatter];
}

it('grants a chatter access idempotently', function (): void {
    [$agent, $chatter] = membershipFixtures();

    $grant = app(GrantAgentMembershipAction::class);
    $grant->execute($agent, $chatter);
    $grant->execute($agent, $chatter);

    expect($agent->chatters()->whereKey($chatter->id)->count())->toBe(1)
        ->and($chatter->canAccessAgent($agent))->toBeTrue();
});

it('refuses to grant a chatter an agent of another workspace', function (): void {
    [, $chatter] = membershipFixtures();
    $foreignAgent = Agent::factory()->forOwner(User::factory()->create())->create();

    expect(fn () => app(GrantAgentMembershipAction::class)->execute($foreignAgent, $chatter))
        ->toThrow(DomainException::class);

    expect($foreignAgent->chatters()->whereKey($chatter->id)->exists())->toBeFalse();
});

it('revokes a chatter access without touching the account', function (): void {
    [$agent, $chatter] = membershipFixtures();

    app(GrantAgentMembershipAction::class)->execute($agent, $chatter);
    app(RevokeAgentMembershipAction::class)->execute($agent, $chatter);

    expect($agent->chatters()->whereKey($chatter->id)->exists())->toBeFalse()
        ->and(Chatter::query()->whereKey($chatter->id)->exists())->toBeTrue();
});
