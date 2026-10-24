<?php

use App\Identity\Workspace\Models\User;

/**
 * Deleting a team role (TeamRoleDeleteController): a role a member still holds
 * is refused as a field error on the form, and stays.
 */
it('refuses to delete a role a member still holds, with a field error', function (): void {
    $owner = User::factory()->create();
    $team = ownerTeam($owner);
    $role = teamRole($team);
    $team->members()->attach(User::factory()->employee($owner)->create()->id, ['role_id' => $role->id]);

    $this->actingAs($owner)
        ->from('/previous')
        ->delete(route('teams.delete-role', ['team' => $team, 'role' => $role->external_id]))
        ->assertRedirect('/previous')
        ->assertSessionHasErrors(['role' => 'Cannot delete this role. 1 member(s) are assigned to it.']);

    expect($role->fresh())->not->toBeNull();
});
