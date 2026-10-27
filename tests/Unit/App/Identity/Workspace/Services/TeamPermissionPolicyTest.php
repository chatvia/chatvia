<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\TeamPermissionPolicy;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;
use Domain\Membership\Team\Models\Team;

/*
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

it('grants a team owner every permission on their own agent', function (): void {
    expect($this->policy->allowsOnAgent($this->owner, $this->agent, 'agent.setup'))->toBeTrue()
        ->and($this->policy->allowsOnAgent($this->owner, $this->agent, 'agent.delete'))->toBeTrue();
});

it('denies a team owner any permission on another workspace agent', function (): void {
    // A team owner's every-permission shortcut covers their own agents only,
    // never another workspace's agent.
    $otherOwner = User::factory()->workspace()->create();
    app(CreateDefaultTeamAction::class)->execute($otherOwner);
    $otherAgent = createAgent($otherOwner);

    expect($this->policy->allowsOnAgent($this->owner, $otherAgent, 'agent.setup'))->toBeFalse()
        ->and($this->policy->allowsOnAgent($this->owner, $otherAgent, 'agent.chat.view'))->toBeFalse();
});

it('gives a teammate only the agent permissions their role grants', function (): void {
    $teammate = teammateWithPermissions($this->owner, permissions: ['agent.setup'], team: $this->team);

    expect($this->policy->allowsOnAgent($teammate, $this->agent, 'agent.setup'))->toBeTrue()
        ->and($this->policy->allowsOnAgent($teammate, $this->agent, 'agent.delete'))->toBeFalse();
});

it('denies a teammate a granted permission on an agent their role does not reach', function (): void {
    $teammate = teammateWithPermissions($this->owner, permissions: ['agent.setup'], team: $this->team, allAgents: false);

    expect($this->policy->allowsOnAgent($teammate, $this->agent, 'agent.setup'))->toBeFalse();
});

it('grants a team owner every team-level permission', function (): void {
    expect($this->policy->allows($this->owner, 'account.security'))->toBeTrue()
        ->and($this->policy->allows($this->owner, 'account.chatters.manage'))->toBeTrue();
});

it('gives a teammate only the team-level permissions their role grants', function (): void {
    $teammate = teammateWithPermissions($this->owner, permissions: ['account.security'], team: $this->team);

    expect($this->policy->allows($teammate, 'account.security'))->toBeTrue()
        ->and($this->policy->allows($teammate, 'account.chatters.manage'))->toBeFalse();
});

it('denies every permission to a user without a team', function (): void {
    $loner = User::factory()->workspace()->create();

    expect($this->policy->allows($loner, 'account.security'))->toBeFalse()
        ->and($this->policy->allowsOnAgent($loner, $this->agent, 'agent.setup'))->toBeFalse();
});

it('grants platform staff no permission in a workspace they are not part of', function (): void {
    $admin = User::factory()->admin()->create();

    expect($this->policy->allows($admin, 'account.security'))->toBeFalse()
        ->and($this->policy->allowsOnAgent($admin, $this->agent, 'agent.setup'))->toBeFalse();
});
