<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Roles\SyncRoleGrantsAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Models\Permission;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Services\TeamService;
use Support\Exceptions\DomainValidationException;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->team = app(TeamService::class)->createDefaultTeam($this->owner);
    $this->role = Role::query()->create([
        'name' => 'Agents',
        'code' => 'agents',
        'team_id' => $this->team->id,
        'guard_name' => Permission::GUARD_NAME,
    ]);
});

it('adds the all-agents meta-permission only from the all-agents switch', function (): void {
    $agent = makeTeamAgent($this->owner, 'Bot', $this->team);
    $this->role->agents()->attach($agent->id);

    app(SyncRoleGrantsAction::class)->execute($this->role, new SaveTeamRoleData(
        team: $this->team,
        name: 'Agents',
        permissions: ['agent.chat.view'],
        allAgents: true,
        agentExternalIds: [],
    ));

    $role = $this->role->fresh();

    expect($role->hasPermissionTo(Permission::ACCESS_ALL_AGENTS))->toBeTrue()
        ->and($role->hasPermissionTo('agent.chat.view'))->toBeTrue()
        ->and($role->agents()->count())->toBe(0);
});

it('rejects agents that are not in the team', function (): void {
    expect(fn () => app(SyncRoleGrantsAction::class)->execute($this->role, new SaveTeamRoleData(
        team: $this->team,
        name: 'Agents',
        permissions: [],
        allAgents: false,
        agentExternalIds: ['not-an-agent'],
    )))->toThrow(DomainValidationException::class, 'One or more selected agents are invalid.');
});
