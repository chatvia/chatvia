<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentAccessPolicy;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Gate;

/*
 * The one agent-access rule for staff accounts: the `access-agent` Gate
 * (dashboard), User::canAccessAgent() (hosted chat) and the chat switcher's
 * list (User::listAccessibleAgents()) all ask AgentAccessPolicy, so they
 * always agree. One case per staff account type, plus the cross-tenant case.
 * Chatters are not users: Chatter::canAccessAgent() checks their own grants
 * (ChatterTest).
 */

beforeEach(function (): void {
    $this->owner = User::factory()->workspace()->create();
    $this->team = app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->update(['current_team_id' => $this->team->id]);
    $this->agent = makeTeamAgent($this->owner, 'Support', $this->team);
    $this->policy = app(AgentAccessPolicy::class);
});

describe('canManage and canChat', function (): void {
    it('lets the workspace owner manage and chat with their own agent', function (): void {
        expect($this->policy->canManage($this->owner, $this->agent))->toBeTrue()
            ->and($this->policy->canChat($this->owner, $this->agent))->toBeTrue()
            ->and(Gate::forUser($this->owner)->allows('access-agent', $this->agent))->toBeTrue()
            ->and($this->owner->canAccessAgent($this->agent))->toBeTrue();
    });

    it('keeps the owner of another workspace out, even with a current team of their own', function (): void {
        // Owning a team of their own gives them no reach into this workspace.
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
        $teammate = teammateWithPermissions($this->owner, [], $this->team);

        expect($this->policy->canManage($teammate, $this->agent))->toBeTrue()
            ->and($this->policy->canChat($teammate, $this->agent))->toBeTrue();
    });

    it('uses the teammate\'s first team when no current team is selected yet', function (): void {
        $teammate = teammateWithPermissions($this->owner, [], $this->team);
        $teammate->update(['current_team_id' => null]);
        $teammate = $teammate->fresh();

        expect($this->policy->canManage($teammate, $this->agent))->toBeTrue()
            ->and($teammate->canAccessAgent($this->agent))->toBeTrue();
    });

    it('refuses a teammate whose role does not reach the agent', function (): void {
        $teammate = teammateWithPermissions($this->owner, [], $this->team, allAgents: false);

        expect($this->policy->canManage($teammate, $this->agent))->toBeFalse()
            ->and($this->policy->canChat($teammate, $this->agent))->toBeFalse();
    });

    it('never lets a chatter grant open the agent for a staff account with the same id', function (): void {
        $teammate = teammateWithPermissions($this->owner, [], $this->team, allAgents: false);
        // Chatter and staff ids come from different tables and can be equal.
        Chatter::factory()->grantedTo($this->agent)->create(['id' => $teammate->id]);

        expect($this->policy->canChat($teammate, $this->agent))->toBeFalse()
            ->and($this->policy->canManage($teammate, $this->agent))->toBeFalse()
            ->and($teammate->canAccessAgent($this->agent))->toBeFalse()
            ->and($this->policy->chattableAgents($teammate)->pluck('id')->all())->not->toContain($this->agent->id);
    });

    it('gives platform staff no reach into another workspace', function (): void {
        $admin = User::factory()->admin()->create();

        expect($this->policy->canManage($admin, $this->agent))->toBeFalse()
            ->and($this->policy->canChat($admin, $this->agent))->toBeFalse()
            ->and($admin->canAccessAgent($this->agent))->toBeFalse()
            ->and(Gate::forUser($admin)->allows('access-agent', $this->agent))->toBeFalse();
    });

    it('lets platform staff manage their own agents like any workspace owner', function (): void {
        $admin = User::factory()->admin()->create();
        $own = createAgent($admin);

        expect($this->policy->canManage($admin, $own))->toBeTrue()
            ->and($this->policy->canChat($admin, $own))->toBeTrue();
    });
});

describe('chattableAgents', function (): void {
    it('lists every agent of the workspace for its owner, whatever the team', function (): void {
        $secondTeam = app(TeamService::class)->createDefaultTeam($this->owner);
        $other = makeTeamAgent($this->owner, 'B', $secondTeam);

        expect($this->policy->chattableAgents($this->owner)->pluck('id')->sort()->values()->all())
            ->toBe(collect([$this->agent->id, $other->id])->sort()->values()->all());
    });

    it('lists the current team\'s agents for a teammate whose role reaches all of them', function (): void {
        $other = makeTeamAgent($this->owner, 'B', $this->team);
        $teammate = teammateWithPermissions($this->owner, [], $this->team);

        expect($this->policy->chattableAgents($teammate)->pluck('id')->sort()->values()->all())
            ->toBe(collect([$this->agent->id, $other->id])->sort()->values()->all());
    });

    it('lists only the agents a scoped role names', function (): void {
        $allowed = makeTeamAgent($this->owner, 'Allowed', $this->team);
        $teammate = teammateWithPermissions($this->owner, [], $this->team, allAgents: false, agentExternalIds: [$allowed->external_id]);

        expect($this->policy->chattableAgents($teammate)->pluck('id')->all())->toBe([$allowed->id]);
    });

    it('lists the first team\'s agents for a teammate without a current team', function (): void {
        $otherTeam = app(TeamService::class)->createDefaultTeam($this->owner);
        makeTeamAgent($this->owner, 'Elsewhere', $otherTeam);
        $teammate = teammateWithPermissions($this->owner, [], $this->team);
        $teammate->update(['current_team_id' => null]);

        expect($this->policy->chattableAgents($teammate->fresh())->pluck('id')->all())->toBe([$this->agent->id]);
    });

    it('lists nothing for a teammate who belongs to no team', function (): void {
        $teammate = User::factory()->employee($this->owner)->create(['current_team_id' => null]);

        expect($this->policy->chattableAgents($teammate)->all())->toBe([]);
    });
});
