<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Agent\Policies\AgentPolicy;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Gate;

/**
 * AgentPolicy matrix. The view/update/delete abilities follow the dashboard
 * access rule (the same as the `access-agent` Gate); create needs the target
 * team's owner.
 *
 *  - Admin             → every agent
 *  - Workspace owner   → only their own agents
 *  - Chatter           → never (chatters don't manage agents)
 *  - Unrelated user    → denied
 */
beforeEach(function (): void {
    $this->policy = app(AgentPolicy::class);
});

it('viewAny is granted only to admins', function (): void {
    expect($this->policy->viewAny(User::factory()->admin()->create()))->toBeTrue()
        ->and($this->policy->viewAny(User::factory()->workspace()->create()))->toBeFalse();
});

it('create is granted only to the owner of the target team', function (): void {
    $workspace = User::factory()->workspace()->create();
    $team = app(TeamService::class)->createDefaultTeam($workspace);
    $otherOwner = User::factory()->workspace()->create();
    $admin = User::factory()->admin()->create();

    expect($this->policy->create($workspace, $team)->allowed())->toBeTrue()
        ->and($this->policy->create($otherOwner, $team)->allowed())->toBeFalse()
        ->and($this->policy->create($admin, $team)->allowed())->toBeFalse();
});

it('create is a not-found when no team is selected', function (): void {
    $response = $this->policy->create(User::factory()->workspace()->create(), null);

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
});

it('view/update/delete grant the workspace owner access to their own agents', function (): void {
    $agent = Agent::factory()->create();
    $owner = $agent->user;

    expect($this->policy->view($owner, $agent))->toBeTrue()
        ->and($this->policy->update($owner, $agent))->toBeTrue()
        ->and($this->policy->delete($owner, $agent))->toBeTrue();
});

it('view/update/delete deny an unrelated workspace owner', function (): void {
    $agent = Agent::factory()->create();
    $intruder = Agent::factory()->create()->user; // different workspace

    expect($this->policy->view($intruder, $agent))->toBeFalse()
        ->and($this->policy->update($intruder, $agent))->toBeFalse()
        ->and($this->policy->delete($intruder, $agent))->toBeFalse();
});

it('agrees with the access-agent Gate, including for staff', function (): void {
    $agent = Agent::factory()->create();
    $admin = User::factory()->admin()->create();
    $intruder = Agent::factory()->create()->user;

    expect($this->policy->view($admin, $agent))->toBe(Gate::forUser($admin)->allows('access-agent', $agent))
        ->and($this->policy->view($admin, $agent))->toBeTrue()
        ->and($this->policy->view($intruder, $agent))->toBe(Gate::forUser($intruder)->allows('access-agent', $agent));
});

it('view denies a chatter that has not been granted access to the agent on the pivot', function (): void {
    $agent = Agent::factory()->create();
    $chatter = User::factory()->chatter($agent->user)->create();

    // Default chatter has no `agent_user` pivot row → no access.
    expect($this->policy->view($chatter, $agent))->toBeFalse()
        ->and($this->policy->update($chatter, $agent))->toBeFalse();
});
