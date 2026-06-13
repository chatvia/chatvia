<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Workspace (account-level) permission gates — dashboard
|--------------------------------------------------------------------------
|
| The dashboard half of the production sign-off. Every account section
| (Connectors, Functions, API keys, Prompts, Chatters, Governance) is gated by
| its `account.*` team permission: the owner has full access; a teammate without
| the permission is blocked; a teammate with it passes.
|
| Integration/Library sections gate INLINE in the controller via
| has_team_permission() → 404 (hide the section's existence). People/Governance
| gate via the `team.permission:*` route middleware → 403. Teams are owner-only
| (DenyIfEmployee). This test pins all three shapes.
*/

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
});

/**
 * A teammate of the owner attached to their team under a role granting exactly
 * $permissions. current_team_id is set so the permission helpers resolve the
 * right team.
 *
 * @param  list<string>  $permissions
 */
function workspaceTeammate(User $owner, array $permissions): User
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

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $member;
}

// [route name, permission] — sections gated INLINE (404 to hide existence)
dataset('inline-gated pages', [
    'connectors edit' => ['integrations.connectors.edit', 'account.connectors'],
    'connectors data' => ['integrations.connectors.data', 'account.connectors'],
    'functions edit' => ['integrations.functions.edit', 'account.functions'],
    'api-keys edit' => ['integrations.api-keys.edit', 'account.api_keys'],
    'prompts edit' => ['library.prompts.edit', 'account.prompts'],
    'prompts list' => ['library.prompts.list', 'account.prompts'],
]);

// [route name, permission] — sections gated by route middleware (403)
dataset('middleware-gated pages', [
    'chatters' => ['people.chatters.index', 'account.chatters.manage'],
    'governance' => ['governance.audit', 'account.governance'],
]);

describe('inline-gated sections (404)', function (): void {

    it('404s a teammate WITHOUT the permission', function (string $route, string $perm): void {
        $member = workspaceTeammate($this->owner, []);

        $this->actingAs($member)->get(route($route))->assertNotFound();
    })->with('inline-gated pages');

    it('allows a teammate WITH the permission', function (string $route, string $perm): void {
        $member = workspaceTeammate($this->owner, [$perm]);

        $this->actingAs($member)->get(route($route))->assertOk();
    })->with('inline-gated pages');

    it('always allows the owner', function (string $route): void {
        $this->actingAs($this->owner)->get(route($route))->assertOk();
    })->with('inline-gated pages');
});

describe('middleware-gated sections (403)', function (): void {

    it('403s a teammate WITHOUT the permission', function (string $route, string $perm): void {
        $member = workspaceTeammate($this->owner, []);

        $this->actingAs($member)->get(route($route))->assertForbidden();
    })->with('middleware-gated pages');

    it('allows a teammate WITH the permission', function (string $route, string $perm): void {
        $member = workspaceTeammate($this->owner, [$perm]);

        $this->actingAs($member)->get(route($route))->assertOk();
    })->with('middleware-gated pages');

    it('always allows the owner', function (string $route): void {
        $this->actingAs($this->owner)->get(route($route))->assertOk();
    })->with('middleware-gated pages');
});

describe('mutations are gated too (not just the section pages)', function (): void {

    it('blocks a teammate without account.api_keys from creating a key, but allows one with it', function (): void {
        // Valid body, so validation passes and the request reaches the gate.
        $without = workspaceTeammate($this->owner, []);
        $this->actingAs($without)
            ->post(route('integrations.api-keys.store'), ['name' => 'CI token'])
            ->assertNotFound();

        $with = workspaceTeammate($this->owner, ['account.api_keys']);
        $this->actingAs($with)
            ->post(route('integrations.api-keys.store'), ['name' => 'CI token'])
            ->assertSessionHasNoErrors();
    });

    it('blocks a teammate without account.prompts from creating a prompt', function (): void {
        $without = workspaceTeammate($this->owner, []);

        // Valid body so validation passes and the request reaches the gate.
        $this->actingAs($without)
            ->post(route('library.prompts.store'), ['name' => 'Greeting', 'prompt' => 'Hello there, how can I help?'])
            ->assertNotFound();
    });
});

describe('team management stays owner-only (DenyIfEmployee)', function (): void {

    it('redirects an employee away from team management — even with account permissions', function (): void {
        // DenyIfEmployee blocks employees from team admin regardless of any grant.
        $member = workspaceTeammate($this->owner, ['account.chatters.manage', 'account.connectors']);

        $this->actingAs($member)->get(route('teams.index'))->assertRedirect(route('dashboard'));
        $this->actingAs($member)->post(route('teams.store'), ['name' => 'X'])->assertRedirect(route('dashboard'));
    });

    it('lets the owner reach team management', function (): void {
        $this->actingAs($this->owner)->get(route('teams.index'))->assertOk();
    });
});
