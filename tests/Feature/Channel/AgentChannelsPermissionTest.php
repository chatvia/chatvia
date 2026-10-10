<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * The agent Channels page used to be gated by `agent.setup`; it now has its own
 * `agent.channels` permission. These pin the split: a teammate needs
 * `agent.channels` specifically — `agent.setup` alone no longer opens it.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();

    $this->agent = createAgent($this->owner);
});

/** Create a teammate whose role grants exactly $permissions on all agents. */
function channelsMember(User $owner, array $permissions): User
{
    $team = $owner->primaryTeam();

    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team,
        name: 'Role-'.Str::random(10),
        permissions: $permissions,
        allAgents: true,
        agentExternalIds: [],
    ));

    $member = User::factory()->employee($owner)->create(['current_team_id' => $team->id]);
    $team->members()->attach($member->id, ['role_id' => $role->id]);

    // Spatie caches the permission→role map for the whole process; reset it so
    // the request resolves against the role we just created.
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $member;
}

it('lets the owner open the channels page', function (): void {
    $this->actingAs($this->owner)
        ->get(route('agents.channels', $this->agent))
        ->assertOk();
});

it('lets a teammate WITH agent.channels open the channels page', function (): void {
    $member = channelsMember($this->owner, ['agent.channels']);

    $this->actingAs($member)
        ->get(route('agents.channels', $this->agent))
        ->assertOk();
});

it('forbids a teammate WITHOUT agent.channels', function (): void {
    $member = channelsMember($this->owner, []);

    $this->actingAs($member)
        ->get(route('agents.channels', $this->agent))
        ->assertForbidden();
});

it('forbids a teammate who has agent.setup but not agent.channels', function (): void {
    // The contrast proves Channels is no longer covered by the Setup grant.
    $member = channelsMember($this->owner, ['agent.setup']);

    $this->actingAs($member)
        ->get(route('agents.channels', $this->agent))
        ->assertForbidden();
});
