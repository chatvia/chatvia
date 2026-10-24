<?php

use App\Identity\Workspace\Models\User;

/**
 * Removing a teammate from the workspace (RemoveTeammateFromWorkspaceController)
 * deletes their account. Another workspace's teammate is a 404
 * (CrossWorkspaceRouteTest).
 */
it('lets the owner delete their own teammate', function (): void {
    $owner = User::factory()->create();
    ownerTeam($owner);
    $teammate = User::factory()->employee($owner)->create();

    $this->actingAs($owner)
        ->from('/')
        ->delete(route('teammates.remove', $teammate))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(User::query()->whereKey($teammate->id)->exists())->toBeFalse();
});
