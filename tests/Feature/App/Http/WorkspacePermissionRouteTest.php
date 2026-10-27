<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;

/*
|--------------------------------------------------------------------------
| Workspace (account-level) permission gates — dashboard
|--------------------------------------------------------------------------
|
| Every account section of the dashboard (Connectors, Functions, API keys,
| Prompts, Chatters, Governance, Security) is gated by its `account.*` team
| permission: the owner has full access; a teammate without the permission is
| blocked; a teammate with it passes.
|
| Integration/Library sections gate via the `team.permission:*,404` route
| middleware → 404 (the `,404` parameter hides the section's existence rather
| than returning the default 403). People/Governance/Security gate via the
| bare `team.permission:*` route middleware → 403. Teams are owner-only
| (DenyIfTeammate), and so is deleting the account (EnsureWorkspaceOwner),
| whatever a teammate's permissions. This test pins every shape.
*/

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(CreateDefaultTeamAction::class)->execute($this->owner);
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
    'security' => ['security.edit', 'account.security'],
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
        withLicense();
        $without = teammateWithPermissions($this->owner, []);
        $this->actingAs($without)
            ->post(route('integrations.api-keys.store'), ['name' => 'CI token'])
            ->assertNotFound();

        $with = teammateWithPermissions($this->owner, ['account.api_keys']);
        $this->actingAs($with)
            ->post(route('integrations.api-keys.store'), ['name' => 'CI token'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('created_token.name', 'CI token');

        expect($with->tokens()->where('name', 'CI token')->count())->toBe(1)
            ->and($without->tokens()->count())->toBe(0);
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

describe('account deletion stays owner-only (EnsureWorkspaceOwner)', function (): void {
    it('forbids a teammate every step of deleting the account, and the account stays', function (string $method, string $route): void {
        $member = teammateWithPermissions($this->owner, ['account.security', 'account.chatters.manage']);

        $this->actingAs($member)
            ->withSession(['account_deletion_confirmed_at' => now()->timestamp])
            ->call($method, route($route), ['password' => 'password'])
            ->assertForbidden();

        expect(User::query()->whereKey([$this->owner->id, $member->id])->count())->toBe(2);
    })->with([
        'the page' => ['GET', 'account.delete'],
        'the email request' => ['POST', 'account.delete.request'],
        'the final click' => ['DELETE', 'account.destroy'],
    ]);
});
