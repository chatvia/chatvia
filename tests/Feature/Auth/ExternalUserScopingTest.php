<?php

use App\Auth\Models\User;
use App\Teams\Models\Team;
use App\Teams\Services\TeamService;
use Domain\Auth\Actions\CreateExternalUserAction;
use Domain\Teams\Actions\AssignUserToRoleAction;
use Domain\Teams\Actions\CreateTeamAction;
use Domain\Teams\Actions\CreateTeamRoleAction;
use Domain\Teams\Data\CreateTeamData;
use Domain\Teams\Data\SaveTeamRoleData;
use Support\Enums\CreatedVia;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
});

it('flags API-created users as external', function (): void {
    $user = app(CreateExternalUserAction::class)->execute($this->owner, 'Jane');

    expect($user->created_via)->toBe(CreatedVia::External)
        ->and($user->isExternal())->toBeTrue();
});

it('does not count external users toward the team member quota', function (): void {
    $before = $this->owner->teamMembersCount();

    app(CreateExternalUserAction::class)->execute($this->owner, 'Jane');
    app(CreateExternalUserAction::class)->execute($this->owner, 'John');

    expect($this->owner->fresh()->teamMembersCount())->toBe($before);
});

it('does not create a team when an external user is created', function (): void {
    $teamsBefore = Team::count();

    app(CreateExternalUserAction::class)->execute($this->owner, 'Jane');

    expect(Team::count())->toBe($teamsBefore);
});

it('excludes external users from the team member list', function (): void {
    $user = app(CreateExternalUserAction::class)->execute($this->owner, 'Jane');

    expect($this->owner->primaryTeam()->members()->where('users.id', $user->id)->exists())->toBeFalse();
});

describe('dashboard access', function (): void {

    it('lets the owner access the dashboard', function (): void {
        expect($this->owner->canAccessDashboard())->toBeTrue();
    });

    it('blocks a member of a chat-only team', function (): void {
        $team = app(CreateTeamAction::class)->execute(
            new CreateTeamData(owner: $this->owner, name: 'Customers', canAccessDashboard: false),
        );
        $role = app(CreateTeamRoleAction::class)->execute(
            new SaveTeamRoleData(team: $team, name: 'Chat', permissions: [], allAgents: true, agentExternalIds: []),
        );
        $user = app(CreateExternalUserAction::class)->execute($this->owner, 'Jane');
        app(AssignUserToRoleAction::class)->execute($team, $role, $user);

        expect($user->fresh()->canAccessDashboard())->toBeFalse();
    });

    it('allows a member of a dashboard team', function (): void {
        $team = $this->owner->primaryTeam();
        $role = app(CreateTeamRoleAction::class)->execute(
            new SaveTeamRoleData(team: $team, name: 'Staff', permissions: [], allAgents: true, agentExternalIds: []),
        );
        $user = app(CreateExternalUserAction::class)->execute($this->owner, 'Sam');
        app(AssignUserToRoleAction::class)->execute($team, $role, $user);

        expect($user->fresh()->canAccessDashboard())->toBeTrue();
    });
});
