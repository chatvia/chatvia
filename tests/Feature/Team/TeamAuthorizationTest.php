<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Invitations\CreateTeamInvitationAction;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Events\TeamInvitationCreated;
use Domain\Membership\Team\Models\Invitation;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Event;

/*
 * Team management lets the owner through each of its gates (TeamPolicy). Another
 * workspace's team is a 404 on every one of these routes, swept by
 * tests/Feature/Isolation/CrossWorkspaceRouteTest.php.
 */

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->outsider = User::factory()->create();

    foreach ([$this->owner, $this->outsider] as $user) {
        app(TeamService::class)->createDefaultTeam($user);
    }
});

describe('teams.update', function (): void {
    it('lets the owner rename their team', function (): void {
        $team = $this->owner->primaryTeam();

        $this->actingAs($this->owner)
            ->from('/')
            ->put(route('teams.update', $team), ['name' => 'Renamed'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($team->fresh()->name)->toBe('Renamed');
    });
});

describe('teams.remove-member', function (): void {
    it('lets the owner through the ownership gate', function (): void {
        $member = User::factory()->employee($this->owner)->create();

        $this->actingAs($this->owner)
            ->from('/')
            ->delete(route('teams.remove-member', [$this->owner->primaryTeam(), $member]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    });
});

describe('teams.destroy', function (): void {
    it('lets the owner delete a non-primary, agentless team', function (): void {
        $team = app(CreateTeamAction::class)->execute(
            new CreateTeamData(workspace: $this->owner->workspace(), name: 'Deletable'),
        );

        $this->actingAs($this->owner)
            ->from('/')
            ->delete(route('teams.destroy', $team))
            ->assertRedirect(route('teams.index'))
            ->assertSessionHasNoErrors();

        expect($team->fresh())->toBeNull();
    });
});

describe('teams.invite', function (): void {
    it('lets the owner invite someone to their team', function (): void {
        $team = $this->owner->primaryTeam();
        app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
            team: $team, name: 'Support', permissions: ['agent.chat.view'], allAgents: true, agentExternalIds: [],
        ));

        $this->actingAs($this->owner)
            ->from('/')
            ->post(route('teams.invite', $team), [
                'email' => 'newbie@example.com',
                'role' => 'support',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($team->invitations()->where('email', 'newbie@example.com')->exists())->toBeTrue();
    });
});

/**
 * A pending invitation on the owner's team.
 *
 * @return array{0: Team, 1: Invitation}
 */
function seedResendInvitation(User $owner): array
{
    $team = $owner->primaryTeam();
    app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team, name: 'Support', permissions: ['agent.chat.view'], allAgents: true, agentExternalIds: [],
    ));
    $invitation = app(CreateTeamInvitationAction::class)->execute($team, 'invitee@example.com', 'support');

    return [$team, $invitation];
}

describe('teams.resend-invitation', function (): void {
    it('forbids an outsider', function (): void {
        [$team, $invitation] = seedResendInvitation($this->owner);

        $this->actingAs($this->outsider)
            ->from('/')
            ->post(route('teams.resend-invitation', [$team, $invitation->id]))
            ->assertNotFound();
    });

    it('lets the owner resend an invitation', function (): void {
        [$team, $invitation] = seedResendInvitation($this->owner);
        Event::fake([TeamInvitationCreated::class]);

        $this->actingAs($this->owner)
            ->from('/')
            ->post(route('teams.resend-invitation', [$team, $invitation->id]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Event::assertDispatched(TeamInvitationCreated::class, fn (TeamInvitationCreated $event): bool => $event->invitation->is($invitation));
    });
});

describe('teammates.remove (workspace-level account deletion)', function (): void {
    it('lets the owner delete their own teammate', function (): void {
        $employee = User::factory()->employee($this->owner)->create();

        $this->actingAs($this->owner)
            ->from('/')
            ->delete(route('teammates.remove', $employee))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(User::whereKey($employee->id)->exists())->toBeFalse();
    });

    it('404s for a teammate in another workspace and leaves them intact (no IDOR)', function (): void {
        $foreignEmployee = User::factory()->employee($this->outsider)->create();

        $this->actingAs($this->owner)
            ->from('/')
            ->delete(route('teammates.remove', $foreignEmployee))
            ->assertNotFound();

        expect(User::whereKey($foreignEmployee->id)->exists())->toBeTrue();
    });
});
