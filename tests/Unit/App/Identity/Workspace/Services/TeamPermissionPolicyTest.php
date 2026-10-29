<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\TeamPermissionPolicy;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;

/**
 * What a team role lets a user do: the rule behind the `team.permission:*`
 * and `agent.permission:*` route middleware. Owners hold every permission in
 * their own team only; teammates need the named permission (and, on an agent,
 * access to it); platform admins hold nothing in a workspace that is not
 * theirs.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->workspace()->create();
    $this->team = app(CreateDefaultTeamAction::class)->execute($this->owner);
    $this->agent = createAgent($this->owner);
    $this->policy = app(TeamPermissionPolicy::class);
});

it('grants a team owner every permission in their own workspace only', function (): void {
    $otherOwner = User::factory()->workspace()->create();
    app(CreateDefaultTeamAction::class)->execute($otherOwner);
    $otherAgent = createAgent($otherOwner);

    expect($this->policy->allowsOnAgent($this->owner, $this->agent, 'agent.setup'))->toBeTrue()
        ->and($this->policy->allowsOnAgent($this->owner, $this->agent, 'agent.delete'))->toBeTrue()
        ->and($this->policy->allows($this->owner, 'account.security'))->toBeTrue()
        ->and($this->policy->allows($this->owner, 'account.chatters.manage'))->toBeTrue()
        ->and($this->policy->allowsOnAgent($this->owner, $otherAgent, 'agent.setup'))->toBeFalse()
        ->and($this->policy->allowsOnAgent($this->owner, $otherAgent, 'agent.chat.view'))->toBeFalse();
});

it('gives a teammate only the permissions their role grants, on the agents it reaches', function (): void {
    $teammate = teammateWithPermissions($this->owner, permissions: ['agent.setup', 'account.security'], team: $this->team);
    $scoped = teammateWithPermissions($this->owner, permissions: ['agent.setup'], team: $this->team, allAgents: false);

    expect($this->policy->allowsOnAgent($teammate, $this->agent, 'agent.setup'))->toBeTrue()
        ->and($this->policy->allowsOnAgent($teammate, $this->agent, 'agent.delete'))->toBeFalse()
        ->and($this->policy->allows($teammate, 'account.security'))->toBeTrue()
        ->and($this->policy->allows($teammate, 'account.chatters.manage'))->toBeFalse()
        ->and($this->policy->allowsOnAgent($scoped, $this->agent, 'agent.setup'))->toBeFalse();
});

it('denies every permission to a user without a team and to platform staff from outside', function (): void {
    $loner = User::factory()->workspace()->create();
    $admin = User::factory()->admin()->create();

    expect($this->policy->allows($loner, 'account.security'))->toBeFalse()
        ->and($this->policy->allowsOnAgent($loner, $this->agent, 'agent.setup'))->toBeFalse()
        ->and($this->policy->allows($admin, 'account.security'))->toBeFalse()
        ->and($this->policy->allowsOnAgent($admin, $this->agent, 'agent.setup'))->toBeFalse();
});
