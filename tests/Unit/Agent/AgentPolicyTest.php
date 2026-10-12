<?php

use App\Identity\Chatter\Models\Chatter;
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
 *  - Admin             → only their own agents (no cross-workspace reach)
 *  - Workspace owner   → only their own agents
 *  - Unrelated user    → denied, even with the id of a chatter granted the agent
 * Chatters are not users and never reach the dashboard policy at all.
 */
beforeEach(function (): void {
    $this->policy = app(AgentPolicy::class);
});

it('viewAny is granted to nobody, platform staff included', function (): void {
    expect($this->policy->viewAny(User::factory()->admin()->create()))->toBeFalse()
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
    $owner = $agent->workspace->owner;

    expect($this->policy->view($owner, $agent))->toBeTrue()
        ->and($this->policy->update($owner, $agent))->toBeTrue()
        ->and($this->policy->delete($owner, $agent))->toBeTrue();
});

it('view/update/delete deny an unrelated workspace owner', function (): void {
    $agent = Agent::factory()->create();
    $intruder = Agent::factory()->create()->workspace->owner; // different workspace

    expect($this->policy->view($intruder, $agent))->toBeFalse()
        ->and($this->policy->update($intruder, $agent))->toBeFalse()
        ->and($this->policy->delete($intruder, $agent))->toBeFalse();
});

it('agrees with the access-agent Gate, including for staff', function (): void {
    $agent = Agent::factory()->create();
    $admin = User::factory()->admin()->create();
    $intruder = Agent::factory()->create()->workspace->owner;

    expect($this->policy->view($admin, $agent))->toBe(Gate::forUser($admin)->allows('access-agent', $agent))
        ->and($this->policy->view($admin, $agent))->toBeFalse()
        ->and($this->policy->view($intruder, $agent))->toBe(Gate::forUser($intruder)->allows('access-agent', $agent));
});

it('denies a staff account whose id equals a chatter granted the agent', function (): void {
    $agent = Agent::factory()->create();
    $intruder = Agent::factory()->create()->workspace->owner; // different workspace
    // Chatter and staff ids come from different tables and can be equal.
    Chatter::factory()->grantedTo($agent)->create(['id' => $intruder->id]);

    expect($this->policy->view($intruder, $agent))->toBeFalse()
        ->and($this->policy->update($intruder, $agent))->toBeFalse()
        ->and($this->policy->delete($intruder, $agent))->toBeFalse();
});
