<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Invitations\CreateTeamInvitationAction;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Events\TeamInvitationCreated;
use Domain\Membership\Team\Models\Invitation;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Event;

/*
 * CreateTeamInvitationAction persists the invitation row and fires
 * TeamInvitationCreated (the mail send rides the listener, never an inline
 * Notification dispatch). The two guards it keeps — "already a member" and
 * "unknown role" — are the only branches, so they earn the coverage alongside
 * the happy path.
 */
function ctiTeamWithSupportRole(): Team
{
    $owner = User::factory()->create(['email' => 'owner@example.com']);

    $team = app(TeamService::class)->createDefaultTeam($owner);

    // Default teams ship only the (never-invitable) `owner` role.
    app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team,
        name: 'Support',
        permissions: ['agent.chat.view'],
        allAgents: true,
        agentExternalIds: [],
    ));

    return $team;
}

it('persists the invitation row, dispatches TeamInvitationCreated, and returns the invitation', function (): void {
    Event::fake([TeamInvitationCreated::class]);

    $team = ctiTeamWithSupportRole();

    $invitation = app(CreateTeamInvitationAction::class)->execute($team, 'invitee@example.com', 'support');

    $role = $team->roles()->where('code', 'support')->firstOrFail();

    expect($invitation)->toBeInstanceOf(Invitation::class)
        ->and($invitation->email)->toBe('invitee@example.com')
        ->and($invitation->role_id)->toBe($role->id)
        ->and($invitation->team_id)->toBe($team->id)
        ->and($team->invitations()->where('email', 'invitee@example.com')->exists())->toBeTrue();

    Event::assertDispatched(
        TeamInvitationCreated::class,
        fn (TeamInvitationCreated $event): bool => $event->email === 'invitee@example.com'
            && $event->invitation->is($invitation),
    );
});

it('rejects inviting someone who already belongs to the team', function (): void {
    Event::fake([TeamInvitationCreated::class]);

    $team = ctiTeamWithSupportRole();

    // The owner counts as a team member via hasUserWithEmail().
    expect(fn () => app(CreateTeamInvitationAction::class)->execute($team, 'owner@example.com', 'support'))
        ->toThrow(RuntimeException::class, 'This user already belongs to the team.');

    expect($team->invitations()->count())->toBe(0);
    Event::assertNotDispatched(TeamInvitationCreated::class);
});

it('rejects an invitation to a role the team does not have', function (): void {
    Event::fake([TeamInvitationCreated::class]);

    $team = ctiTeamWithSupportRole();

    expect(fn () => app(CreateTeamInvitationAction::class)->execute($team, 'invitee@example.com', 'nonexistent-role'))
        ->toThrow(RuntimeException::class, "The role 'nonexistent-role' does not exist for this team.");

    expect($team->invitations()->count())->toBe(0);
    Event::assertNotDispatched(TeamInvitationCreated::class);
});
