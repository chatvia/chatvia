<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;

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
| Integration/Library sections gate via the `team.permission:*,404` route
| middleware → 404 (the `,404` parameter hides the section's existence rather
| than returning the default 403). People/Governance gate via the bare
| `team.permission:*` route middleware → 403. Teams are owner-only
| (DenyIfTeammate). This test pins all three shapes.
*/

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
});

// [route name, permission] — sections gated by `team.permission:*,404` route
// middleware (404 to hide existence)
dataset('route-404-gated pages', [
    'connectors edit' => ['integrations.connectors.edit', 'account.connectors'],
    'connectors data' => ['integrations.connectors.data', 'account.connectors'],
    'functions edit' => ['integrations.functions.edit', 'account.functions'],
    'api-keys edit' => ['integrations.api-keys.edit', 'account.api_keys'],
    'prompts edit' => ['library.prompts.edit', 'account.prompts'],
    'prompts list' => ['library.prompts.list', 'account.prompts'],
]);

// [route name, permission] — sections gated by the bare `team.permission:*`
// route middleware (403, no `,404` parameter)
dataset('middleware-gated pages', [
    'chatters' => ['people.chatters.index', 'account.chatters.manage'],
    'governance' => ['governance.audit', 'account.governance'],
]);

describe('route-404-gated sections (404)', function (): void {

    it('404s a teammate WITHOUT the permission', function (string $route, string $perm): void {
        $member = teammateWithPermissions($this->owner, []);

        $this->actingAs($member)->get(route($route))->assertNotFound();
    })->with('route-404-gated pages');

    it('allows a teammate WITH the permission', function (string $route, string $perm): void {
        $member = teammateWithPermissions($this->owner, [$perm]);

        $this->actingAs($member)->get(route($route))->assertOk();
    })->with('route-404-gated pages');

    it('always allows the owner', function (string $route): void {
        $this->actingAs($this->owner)->get(route($route))->assertOk();
    })->with('route-404-gated pages');
});

describe('middleware-gated sections (403)', function (): void {

    it('403s a teammate WITHOUT the permission', function (string $route, string $perm): void {
        $member = teammateWithPermissions($this->owner, []);

        $this->actingAs($member)->get(route($route))->assertForbidden();
    })->with('middleware-gated pages');

    it('allows a teammate WITH the permission', function (string $route, string $perm): void {
        $member = teammateWithPermissions($this->owner, [$perm]);

        $this->actingAs($member)->get(route($route))->assertOk();
    })->with('middleware-gated pages');

    it('always allows the owner', function (string $route): void {
        $this->actingAs($this->owner)->get(route($route))->assertOk();
    })->with('middleware-gated pages');
});

describe('mutations are gated too (not just the section pages)', function (): void {

    it('blocks a teammate without account.api_keys from creating a key, but allows one with it', function (): void {
        // Valid body, so validation passes and the request reaches the gate.
        $without = teammateWithPermissions($this->owner, []);
        $this->actingAs($without)
            ->post(route('integrations.api-keys.store'), ['name' => 'CI token'])
            ->assertNotFound();

        $with = teammateWithPermissions($this->owner, ['account.api_keys']);
        $this->actingAs($with)
            ->post(route('integrations.api-keys.store'), ['name' => 'CI token'])
            ->assertSessionHasNoErrors();
    });

    it('blocks a teammate without account.prompts from creating a prompt', function (): void {
        $without = teammateWithPermissions($this->owner, []);

        // Valid body so validation passes and the request reaches the gate.
        $this->actingAs($without)
            ->post(route('library.prompts.store'), ['name' => 'Greeting', 'prompt' => 'Hello there, how can I help?'])
            ->assertNotFound();
    });
});

describe('team management stays owner-only (DenyIfTeammate)', function (): void {

    it('redirects an employee away from team management — even with account permissions', function (): void {
        // DenyIfTeammate blocks employees from team admin regardless of any grant.
        $member = teammateWithPermissions($this->owner, ['account.chatters.manage', 'account.connectors']);

        $this->actingAs($member)->get(route('teams.index'))->assertRedirect(route('dashboard'));
        $this->actingAs($member)->post(route('teams.store'), ['name' => 'X'])->assertRedirect(route('dashboard'));
    });

    it('lets the owner reach team management', function (): void {
        $this->actingAs($this->owner)->get(route('teams.index'))->assertOk();
    });
});
