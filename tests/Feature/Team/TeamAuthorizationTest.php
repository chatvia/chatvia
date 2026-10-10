<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserQuota;
use Domain\Membership\Team\Actions\Invitations\CreateTeamInvitationAction;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamService;

/*
 * Pins the team-management AUTHORIZATION outcome — owner allowed, non-owner
 * forbidden — across the three enforcement styles these controllers use:
 *   1. FormRequest::authorize()       (teams.update)
 *   2. inline abort(403)              (teams.remove-member)
 *   3. inline back()->withErrors()    (teams.destroy)
 * All three gate on ownsTeam(), which is exactly what TeamPolicy centralises,
 * so this is the safety net for routing those checks through the policy.
 */

beforeEach(function (): void {
    // Registration is trial-checkout-only now (the Stripe webhook provisions
    // billing/quota/default team), so factory users start bare — provision the
    // pieces these routes rely on explicitly.
    $this->owner = User::factory()->create();
    $this->outsider = User::factory()->create();

    foreach ([$this->owner, $this->outsider] as $user) {
        app(TeamService::class)->createDefaultTeam($user);
        $user->billing()->firstOrCreate([]);
        UserQuota::firstOrCreate(
            ['user_id' => $user->id],
            planFixedLimits(),
        );
    }

    // Headroom to spin up a second, deletable team (the primary can't be deleted).
    $this->owner->quota()->update(['teams_limit' => 10]);
});

describe('teams.update (FormRequest::authorize)', function (): void {
    it('forbids a non-owner', function (): void {
        $this->actingAs($this->outsider)
            ->from('/')
            ->put(route('teams.update', $this->owner->primaryTeam()), ['name' => 'Hijacked'])
            ->assertForbidden();
    });

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

describe('teams.remove-member (inline abort 403)', function (): void {
    it('forbids a non-owner', function (): void {
        $member = User::factory()->create();

        $this->actingAs($this->outsider)
            ->from('/')
            ->delete(route('teams.remove-member', [$this->owner->primaryTeam(), $member]))
            ->assertForbidden();
    });

    it('lets the owner through the ownership gate', function (): void {
        $member = User::factory()->create();

        $this->actingAs($this->owner)
            ->from('/')
            ->delete(route('teams.remove-member', [$this->owner->primaryTeam(), $member]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    });
});

describe('teams.destroy (inline back()->withErrors)', function (): void {
    it('forbids a non-owner and leaves the team intact', function (): void {
        $team = app(CreateTeamAction::class)->execute(
            new CreateTeamData(owner: $this->owner, name: 'Deletable'),
        );

        $this->actingAs($this->outsider)
            ->from('/')
            ->delete(route('teams.destroy', $team))
            ->assertSessionHasErrors('team');

        expect($team->fresh())->not->toBeNull();
    });

    it('lets the owner delete a non-primary, agentless team', function (): void {
        $team = app(CreateTeamAction::class)->execute(
            new CreateTeamData(owner: $this->owner, name: 'Deletable'),
        );

        $this->actingAs($this->owner)
            ->from('/')
            ->delete(route('teams.destroy', $team))
            ->assertRedirect(route('teams.index'))
            ->assertSessionHasNoErrors();

        expect($team->fresh())->toBeNull();
    });
});

describe('teams.invite (FormRequest::authorize, permission-aware)', function (): void {
    it('forbids an outsider', function (): void {
        $this->actingAs($this->outsider)
            ->from('/')
            ->post(route('teams.invite', $this->owner->primaryTeam()), [
                'email' => 'newbie@example.com',
                'role' => 'admin',
            ])
            ->assertForbidden();
    });

    it('lets the owner through the invite gate', function (): void {
        // Owners pass authorize() regardless of payload validity; asserting a
        // redirect (not a 403) proves the gate opened. The permission-aware
        // branch (team.invite grant) is preserved verbatim in TeamPolicy.
        $this->actingAs($this->owner)
            ->from('/')
            ->post(route('teams.invite', $this->owner->primaryTeam()), [
                'email' => 'newbie@example.com',
                'role' => 'admin',
            ])
            ->assertRedirect();
    });
});

// Seed a pending invitation on the owner's team. CreateTeamInvitationAction has
// no subscription gate, so this works for the bare beforeEach owner.
function seedResendInvitation(User $owner): array
{
    $team = $owner->primaryTeam();
    app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team, name: 'Support', permissions: ['agent.chat.view'], allAgents: true, agentExternalIds: [],
    ));
    $invitation = app(CreateTeamInvitationAction::class)->execute($team, 'invitee@example.com', 'support');

    return [$team, $invitation];
}

describe('teams.resend-invitation (inline abort 403)', function (): void {
    it('forbids an outsider', function (): void {
        [$team, $invitation] = seedResendInvitation($this->owner);

        $this->actingAs($this->outsider)
            ->from('/')
            ->post(route('teams.resend-invitation', [$team, $invitation->id]))
            ->assertForbidden();
    });

    it('lets the owner through the resend gate', function (): void {
        // Mirrors teams.invite: the bare owner has no active subscription, so the
        // resend's own subscription gate returns back()->withErrors — still a
        // redirect, not a 403. Asserting redirect-not-forbidden proves the
        // addTeamMember authorization opened; the success path + the
        // subscription gate are pinned in ResendTeamInvitationActionTest.
        [$team, $invitation] = seedResendInvitation($this->owner);

        $this->actingAs($this->owner)
            ->from('/')
            ->post(route('teams.resend-invitation', [$team, $invitation->id]))
            ->assertRedirect();
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
