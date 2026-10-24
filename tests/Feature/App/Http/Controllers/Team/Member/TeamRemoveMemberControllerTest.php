<?php

use App\Identity\Workspace\Models\User;

/**
 * Taking a member off a team (TeamRemoveMemberController): the membership
 * goes, the account stays.
 */
it('lets the owner remove a member from the team and keeps the account', function (): void {
    $owner = User::factory()->create();
    $team = ownerTeam($owner);
    $member = teammateWithPermissions($owner, ['agent.chat.view'], $team);

    $this->actingAs($owner)
        ->from('/')
        ->delete(route('teams.remove-member', [$team, $member]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($team->members()->whereKey($member->id)->exists())->toBeFalse()
        ->and($member->fresh())->not->toBeNull();
});
