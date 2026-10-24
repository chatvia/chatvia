<?php

use App\Identity\Workspace\Models\User;

/**
 * Cancelling a pending team invitation (TeamCancelInvitationController): the
 * owner deletes the row; a teammate is sent back to the dashboard and the
 * row stays. Another workspace's invitation is a 404 (CrossWorkspaceRouteTest).
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->team = ownerTeam($this->owner);
    $this->invitation = $this->team->invitations()->create([
        'email' => 'pending@example.com',
        'role_id' => teamRole($this->team)->id,
    ]);
});

it('lets the owner cancel a pending invitation', function (): void {
    $this->actingAs($this->owner)
        ->from('/')
        ->delete(route('teams.cancel-invitation', [$this->team, $this->invitation->id]))
        ->assertRedirect('/')
        ->assertSessionHasNoErrors();

    expect($this->team->invitations()->whereKey($this->invitation->id)->exists())->toBeFalse();
});

it('sends a teammate back to the dashboard and keeps the invitation', function (): void {
    $teammate = teammateWithPermissions($this->owner, ['team.invite'], $this->team);

    $this->actingAs($teammate)
        ->from('/')
        ->delete(route('teams.cancel-invitation', [$this->team, $this->invitation->id]))
        ->assertRedirect(route('dashboard'));

    expect($this->team->invitations()->whereKey($this->invitation->id)->exists())->toBeTrue();
});
