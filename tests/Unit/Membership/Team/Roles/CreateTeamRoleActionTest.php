<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Models\Permission;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Services\TeamService;
use Support\Exceptions\DomainValidationException;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->team = app(TeamService::class)->createDefaultTeam($this->owner);
});

function saveRoleData(object $test, string $name, array $agentIds = [], bool $allAgents = false): SaveTeamRoleData
{
    return new SaveTeamRoleData(
        team: $test->team,
        name: $name,
        permissions: ['agent.chat.view'],
        allAgents: $allAgents,
        agentExternalIds: $agentIds,
    );
}

it('creates a scoped role with its code, permissions and agents', function (): void {
    $agent = makeTeamAgent($this->owner, 'Bot', $this->team);

    $role = app(CreateTeamRoleAction::class)->execute(saveRoleData($this, 'Support Desk', [$agent->external_id]));

    expect($role->code)->toBe('support_desk')
        ->and($role->guard_name)->toBe(Permission::GUARD_NAME)
        ->and($role->hasPermissionTo('agent.chat.view'))->toBeTrue()
        ->and($role->hasPermissionTo(Permission::ACCESS_ALL_AGENTS))->toBeFalse()
        ->and($role->agents()->pluck('agents.id')->all())->toBe([$agent->id]);
});

it('rejects a second role with the same name', function (): void {
    app(CreateTeamRoleAction::class)->execute(saveRoleData($this, 'Support', allAgents: true));

    expect(fn () => app(CreateTeamRoleAction::class)->execute(saveRoleData($this, 'Support', allAgents: true)))
        ->toThrow(DomainValidationException::class, 'A role with this name already exists.');
});

it('creates nothing when a chosen agent is not in the team', function (): void {
    $stranger = User::factory()->create();
    $foreignAgent = makeTeamAgent($stranger, 'Elsewhere');

    try {
        app(CreateTeamRoleAction::class)->execute(saveRoleData($this, 'Scoped', [$foreignAgent->external_id]));
        $this->fail('Expected the foreign agent to be rejected.');
    } catch (DomainValidationException $e) {
        expect($e->field)->toBe('agent_ids');
    }

    expect(Role::query()->where('team_id', $this->team->id)->where('code', 'scoped')->exists())->toBeFalse();
});
