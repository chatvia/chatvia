<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;

/**
 * Creating a team from the Teams page (TeamsStoreController). Without a
 * license key the workspace keeps only the team it starts with; any paid key
 * lifts that. Teammates never reach team management
 * (WorkspacePermissionRouteTest).
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    ownerTeam($this->owner);
});

it('refuses a second team without a license key', function (): void {
    $this->actingAs($this->owner)
        ->post(route('teams.store'), ['name' => 'Support'])
        ->assertForbidden();

    expect($this->owner->workspace()->teams()->count())->toBe(1);
});

it('creates more teams with a paid license key', function (Closure $license): void {
    $license();

    $this->actingAs($this->owner)
        ->post(route('teams.store'), ['name' => 'Support'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->owner->workspace()->teams()->pluck('name'))->toContain('Support')
        ->and($this->owner->workspace()->teams()->count())->toBe(2);
})->with([
    'Pro' => [fn () => withLicense()],
    'Enterprise' => [fn () => withEnterpriseLicense()],
]);

it('keeps teams made with a key usable after the key is removed', function (): void {
    withLicense();

    $this->actingAs($this->owner)->post(route('teams.store'), ['name' => 'Support']);
    $team = Team::query()->where('name', 'Support')->firstOrFail();

    saveLicenseKey('');

    $this->actingAs($this->owner)
        ->from('/')
        ->put(route('teams.update', $team), ['name' => 'Customer care'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($this->owner)
        ->post(route('teams.store'), ['name' => 'Sales'])
        ->assertForbidden();

    expect($team->fresh()->name)->toBe('Customer care');
});
