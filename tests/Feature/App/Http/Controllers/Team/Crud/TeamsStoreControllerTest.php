<?php

use App\Identity\Workspace\Models\User;

/**
 * Creating a team from the Teams page (TeamsStoreController). Teams have no
 * free-use limit, so a workspace without a license key may add as many as it
 * likes. Teammates never reach team management (WorkspacePermissionRouteTest).
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    ownerTeam($this->owner);
});

it('creates a second team without a license key', function (): void {
    withoutLicense();

    $this->actingAs($this->owner)
        ->post(route('teams.store'), ['name' => 'Support'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->owner->workspace()->teams()->pluck('name'))->toContain('Support')
        ->and($this->owner->workspace()->teams()->count())->toBe(2);
});
