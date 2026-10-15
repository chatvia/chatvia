<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Agent\Policies\AgentPolicy;
use Domain\Membership\Team\Services\TeamService;

/**
 * AgentPolicy: nobody lists every agent, create needs the target team's
 * owner, and view/update/delete hand over to the dashboard access rule
 * (AgentAccessPolicy::canManage(), whose cases have their own test).
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

it('view/update/delete follow the dashboard access rule', function (): void {
    $agent = Agent::factory()->create();
    $owner = $agent->workspace->owner;
    $intruder = Agent::factory()->create()->workspace->owner; // different workspace

    expect($this->policy->view($owner, $agent))->toBeTrue()
        ->and($this->policy->update($owner, $agent))->toBeTrue()
        ->and($this->policy->delete($owner, $agent))->toBeTrue()
        ->and($this->policy->view($intruder, $agent))->toBeFalse()
        ->and($this->policy->update($intruder, $agent))->toBeFalse()
        ->and($this->policy->delete($intruder, $agent))->toBeFalse();
});
