<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Invitations\CreateTeamInvitationAction;
use Domain\Membership\Team\Actions\Invitations\ResendTeamInvitationAction;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Events\TeamInvitationCreated;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Support\Exceptions\DomainValidationException;

/*
 * ResendTeamInvitationAction re-fires the mail for an existing pending
 * invitation: it refreshes the "Sent" timestamp (re-arming the 7-day accept
 * window) and dispatches TeamInvitationCreated — the same event the initial
 * invite uses, so the mail rides the existing listener. No new row is minted.
 * It gates on the same active-subscription requirement as the initial invite.
 */
function rtiTeamWithSupportRole(bool $withSubscription = true): Team
{
    $owner = User::factory()->create(['email' => 'owner@example.com']);

    if ($withSubscription) {
        // hasActiveSubscription() gates resends — seed an active subscription
        // the same way InviteTeamMemberActionTest does.
        $owner->billing()->firstOrCreate([])->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_test_'.Str::random(8),
            'stripe_status' => 'active',
            'stripe_price' => 'price_test_'.Str::random(6),
            'quantity' => 1,
        ]);
        $owner->refresh();
    }

    $team = app(TeamService::class)->createDefaultTeam($owner);

    app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team,
        name: 'Support',
        permissions: ['agent.chat.view'],
        allAgents: true,
        agentExternalIds: [],
    ));

    return $team;
}

it('re-dispatches TeamInvitationCreated for the existing invitation without creating a new row', function (): void {
    $team = rtiTeamWithSupportRole();

    $invitation = app(CreateTeamInvitationAction::class)->execute($team, 'invitee@example.com', 'support');

    // Fake only after the initial create so we observe the resend in isolation.
    Event::fake([TeamInvitationCreated::class]);

    app(ResendTeamInvitationAction::class)->execute($invitation);

    expect($team->invitations()->where('email', 'invitee@example.com')->count())->toBe(1);

    Event::assertDispatched(
        TeamInvitationCreated::class,
        fn (TeamInvitationCreated $event): bool => $event->email === 'invitee@example.com'
            && $event->invitation->is($invitation),
    );
});

it('refreshes the sent timestamp so the pending list reflects the latest send and the accept window resets', function (): void {
    $team = rtiTeamWithSupportRole();

    $invitation = app(CreateTeamInvitationAction::class)->execute($team, 'invitee@example.com', 'support');

    // Backdate the original send past the 7-day window, then confirm the resend moves it forward.
    $invitation->forceFill(['created_at' => now()->subDays(10)])->save();
    $original = $invitation->fresh()->created_at;

    Event::fake([TeamInvitationCreated::class]);

    app(ResendTeamInvitationAction::class)->execute($invitation);

    expect($invitation->fresh()->created_at->greaterThan($original))->toBeTrue();
});

it('refuses to resend when the team owner has no active subscription', function (): void {
    $team = rtiTeamWithSupportRole(withSubscription: false);

    $invitation = app(CreateTeamInvitationAction::class)->execute($team, 'invitee@example.com', 'support');

    Event::fake([TeamInvitationCreated::class]);

    expect(fn () => app(ResendTeamInvitationAction::class)->execute($invitation))
        ->toThrow(DomainValidationException::class, 'You need an active subscription to resend invitations.');

    Event::assertNotDispatched(TeamInvitationCreated::class);
});
