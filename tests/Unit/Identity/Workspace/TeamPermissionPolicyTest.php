<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\TeamPermissionPolicy;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;

/*
 * What a team role lets a user do: the rule behind the `team.permission:*`
 * and `agent.permission:*` route middleware. Owners hold every permission in
 * their own team only; teammates need the named permission (and, on an agent,
 * access to it); admins hold everything.
 */

beforeEach(function (): void {
    $this->owner = User::factory()->workspace()->create();
    $this->team = app(TeamService::class)->createDefaultTeam($this->owner);
    $this->agent = createAgent($this->owner);
    $this->policy = app(TeamPermissionPolicy::class);
});

it('grants a team owner every permission on their own agent', function (): void {
    expect($this->policy->allowsOnAgent($this->owner, $this->agent, 'agent.setup'))->toBeTrue()
        ->and($this->policy->allowsOnAgent($this->owner, $this->agent, 'agent.delete'))->toBeTrue();
});

it('denies a team owner any permission on another workspace agent', function (): void {
    // Regression: a team owner used to get every permission on ANY agent while
    // they had a current team — including another workspace's agent.
    $otherOwner = User::factory()->workspace()->create();
    app(TeamService::class)->createDefaultTeam($otherOwner);
    $otherAgent = createAgent($otherOwner);

    expect($this->policy->allowsOnAgent($this->owner, $otherAgent, 'agent.setup'))->toBeFalse()
        ->and($this->policy->allowsOnAgent($this->owner, $otherAgent, 'agent.chat.view'))->toBeFalse();
});

it('gives a teammate only the agent permissions their role grants', function (): void {
    $teammate = teammateWithPermissions($this->owner, team: $this->team, permissions: ['agent.setup']);

    expect($this->policy->allowsOnAgent($teammate, $this->agent, 'agent.setup'))->toBeTrue()
        ->and($this->policy->allowsOnAgent($teammate, $this->agent, 'agent.delete'))->toBeFalse();
});

it('denies a teammate a granted permission on an agent their role does not reach', function (): void {
    $teammate = teammateWithPermissions($this->owner, team: $this->team, permissions: ['agent.setup'], allAgents: false);

    expect($this->policy->allowsOnAgent($teammate, $this->agent, 'agent.setup'))->toBeFalse();
});

it('grants a team owner every team-level permission', function (): void {
    expect($this->policy->allows($this->owner, 'account.security'))->toBeTrue()
        ->and($this->policy->allows($this->owner, 'account.chatters.manage'))->toBeTrue();
});

it('gives a teammate only the team-level permissions their role grants', function (): void {
    $teammate = teammateWithPermissions($this->owner, team: $this->team, permissions: ['account.security']);

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
