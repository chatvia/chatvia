<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\GrantAgentMembershipAction;

/**
 * Granting a chatter an agent (the agent's Members tab and the workspace
 * Members page) adds one `agent_chatter` row however often it runs, and only
 * ever for an agent of the chatter's own workspace.
 */
it('grants a chatter the agent once, however often it runs', function (): void {
    $agent = Agent::factory()->create();
    $chatter = Chatter::factory()->ofWorkspace($agent->workspace)->create();

    app(GrantAgentMembershipAction::class)->execute($agent, $chatter);
    app(GrantAgentMembershipAction::class)->execute($agent, $chatter);

    expect($agent->chatters()->whereKey($chatter->id)->count())->toBe(1)
        ->and($chatter->canAccessAgent($agent))->toBeTrue();
});

it('refuses to grant a chatter an agent of another workspace', function (): void {
    $chatter = Chatter::factory()->create();
    $foreignAgent = Agent::factory()->forOwner(User::factory()->create())->create();

    expect(fn () => app(GrantAgentMembershipAction::class)->execute($foreignAgent, $chatter))
        ->toThrow(DomainException::class);

    expect($foreignAgent->chatters()->whereKey($chatter->id)->exists())->toBeFalse();
});
