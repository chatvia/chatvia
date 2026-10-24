<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Invitations\CreateTeamInvitationAction;
use Domain\Membership\Team\Events\TeamInvitationCreated;
use Illuminate\Support\Facades\Event;

/**
 * Sending a pending team invitation again (TeamResendInvitationController)
 * fires the same event as the first send, so the email goes out again.
 */
it('lets the owner resend an invitation', function (): void {
    $owner = User::factory()->create();
    $team = ownerTeam($owner);
    teamRole($team);
    $invitation = app(CreateTeamInvitationAction::class)->execute($team, 'invitee@example.com', 'support');
    Event::fake([TeamInvitationCreated::class]);

    $this->actingAs($owner)
        ->from('/')
        ->post(route('teams.resend-invitation', [$team, $invitation->id]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Event::assertDispatched(TeamInvitationCreated::class, fn (TeamInvitationCreated $event): bool => $event->invitation->is($invitation));
});
