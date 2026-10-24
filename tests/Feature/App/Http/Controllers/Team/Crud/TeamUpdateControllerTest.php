<?php

use App\Identity\Workspace\Models\User;

/**
 * Renaming a team (TeamUpdateController). Another workspace's team is a 404
 * (CrossWorkspaceRouteTest); teammates never reach team management
 * (WorkspacePermissionRouteTest).
 */
it('lets the owner rename their team', function (): void {
    $owner = User::factory()->create();
    $team = ownerTeam($owner);

    $this->actingAs($owner)
        ->from('/')
        ->put(route('teams.update', $team), ['name' => 'Renamed'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($team->fresh()->name)->toBe('Renamed');
});
