<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentAccessPolicy;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Gate;

/*
 * The one agent-access rule: the `access-agent` Gate (dashboard) and
 * User::canAccessAgent() (hosted chat) both ask AgentAccessPolicy, so they can
 * no longer disagree. One case per account type, plus the cross-tenant case.
 */

beforeEach(function (): void {
    $this->owner = User::factory()->workspace()->create();
    $this->team = app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->update(['current_team_id' => $this->team->id]);
    $this->agent = makeTeamAgent($this->owner, 'Support', $this->team);
    $this->policy = app(AgentAccessPolicy::class);
});

function aapTeammate(User $owner, $team, bool $allAgents, ?int $currentTeamId): User
{
    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team,
        name: $allAgents ? 'All agents' : 'No agents',
        permissions: [],
        allAgents: $allAgents,
        agentExternalIds: [],
    ));

    $teammate = User::factory()->employee($owner)->create(['current_team_id' => $currentTeamId]);
    $team->members()->attach($teammate->id, ['role_id' => $role->id]);

    return $teammate->fresh();
}

it('lets the workspace owner manage and chat with their own agent', function (): void {
    expect($this->policy->canManage($this->owner, $this->agent))->toBeTrue()
        ->and($this->policy->canChat($this->owner, $this->agent))->toBeTrue()
        ->and(Gate::forUser($this->owner)->allows('access-agent', $this->agent))->toBeTrue()
        ->and($this->owner->canAccessAgent($this->agent))->toBeTrue();
});

it('keeps the owner of another workspace out, even with a current team of their own', function (): void {
    // Regression: owning *a* team used to be enough for the chat check, so
    // any workspace owner could chat with another tenant's private agent.
    $intruder = User::factory()->workspace()->create();
    $intruderTeam = app(TeamService::class)->createDefaultTeam($intruder);
    $intruder->update(['current_team_id' => $intruderTeam->id]);
    $intruder = $intruder->fresh();

    expect($this->policy->canManage($intruder, $this->agent))->toBeFalse()
        ->and($this->policy->canChat($intruder, $this->agent))->toBeFalse()
        ->and($intruder->canAccessAgent($this->agent))->toBeFalse()
        ->and(Gate::forUser($intruder)->allows('access-agent', $this->agent))->toBeFalse();
});

it('lets a teammate whose role reaches the agent manage and chat with it', function (): void {
    $teammate = aapTeammate($this->owner, $this->team, allAgents: true, currentTeamId: $this->team->id);

    expect($this->policy->canManage($teammate, $this->agent))->toBeTrue()
        ->and($this->policy->canChat($teammate, $this->agent))->toBeTrue();
});

it('uses the teammate\'s first team when no current team is selected yet', function (): void {
    $teammate = aapTeammate($this->owner, $this->team, allAgents: true, currentTeamId: null);

    expect($this->policy->canManage($teammate, $this->agent))->toBeTrue()
        ->and($teammate->canAccessAgent($this->agent))->toBeTrue();
});

it('refuses a teammate whose role does not reach the agent', function (): void {
    $teammate = aapTeammate($this->owner, $this->team, allAgents: false, currentTeamId: $this->team->id);

    expect($this->policy->canManage($teammate, $this->agent))->toBeFalse()
        ->and($this->policy->canChat($teammate, $this->agent))->toBeFalse();
});

it('lets a chatter chat only with granted agents and never manage them', function (): void {
    $other = makeTeamAgent($this->owner, 'Other', $this->team);
    $chatter = User::factory()->chatter($this->owner)->create();
    $chatter->accessibleAgents()->attach($this->agent->id);

    expect($this->policy->canChat($chatter, $this->agent))->toBeTrue()
        ->and($this->policy->canChat($chatter, $other))->toBeFalse()
        ->and($this->policy->canManage($chatter, $this->agent))->toBeFalse()
        ->and(Gate::forUser($chatter)->allows('access-agent', $this->agent))->toBeFalse()
        ->and($this->policy->chattableAgents($chatter)->pluck('id')->all())->toBe([$this->agent->id]);
});

it('lets platform staff manage and chat with any agent', function (): void {
    $admin = User::factory()->admin()->create();

    expect($this->policy->canManage($admin, $this->agent))->toBeTrue()
        ->and($this->policy->canChat($admin, $this->agent))->toBeTrue()
        ->and($admin->canAccessAgent($this->agent))->toBeTrue();
});
