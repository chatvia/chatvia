<?php

use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\GrantAgentMembershipAction;
use Domain\Membership\Agent\Actions\RevokeAgentMembershipAction;

/**
 * Grant/Revoke share the agent_user pivot toggle across the agent-scoped
 * Members tab and the workspace Members settings, so the idempotent
 * attach/detach behaviour is pinned here.
 */
function membershipFixtures(): array
{
    $workspace = User::factory()->create(['type' => UserType::Workspace]);
    $agent = Agent::factory()->create(['user_id' => $workspace->id]);
    $chatter = User::factory()->create([
        'type' => UserType::Chatter,
        'workspace_id' => $workspace->id,
    ]);

    return [$agent, $chatter];
}

it('grants a chatter access idempotently', function (): void {
    [$agent, $chatter] = membershipFixtures();

    $grant = app(GrantAgentMembershipAction::class);
    $grant->execute($agent, $chatter);
    $grant->execute($agent, $chatter);

    expect($agent->chatters()->whereKey($chatter->id)->count())->toBe(1);
});

it('revokes a chatter access without touching the account', function (): void {
    [$agent, $chatter] = membershipFixtures();

    app(GrantAgentMembershipAction::class)->execute($agent, $chatter);
    app(RevokeAgentMembershipAction::class)->execute($agent, $chatter);

    expect($agent->chatters()->whereKey($chatter->id)->exists())->toBeFalse()
        ->and(User::query()->whereKey($chatter->id)->exists())->toBeTrue();
});
